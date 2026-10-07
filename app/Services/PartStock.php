<?php

namespace App\Services;

use App\Enums\PartMovementType;
use App\Models\Part;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * All stock changes go through here so parts.quantity_on_hand and its part_movements log never drift apart.
 */
class PartStock
{
    public function receive(Part $part, int $quantity, User $user, ?string $note = null): void
    {
        $part->increment('quantity_on_hand', $quantity);
        $part->movements()->create(['type' => PartMovementType::In, 'quantity' => $quantity, 'note' => $note, 'created_by' => $user->id]);
    }

    /**
     * @throws ValidationException when the warehouse doesn't have enough on hand.
     */
    public function issue(Part $part, int $quantity, User $user, ?string $note = null): void
    {
        if ($quantity > $part->quantity_on_hand) {
            throw ValidationException::withMessages([
                'quantity' => "Kho chỉ còn {$part->quantity_on_hand} {$part->unit} \"{$part->name}\".",
            ]);
        }

        $part->decrement('quantity_on_hand', $quantity);
        $part->movements()->create(['type' => PartMovementType::Out, 'quantity' => $quantity, 'note' => $note, 'created_by' => $user->id]);
    }

    /**
     * Puts stock back that was issued but never actually used (a quote line deleted, rejected or cancelled).
     */
    public function restore(Part $part, int $quantity, User $user, ?string $note = null): void
    {
        $part->increment('quantity_on_hand', $quantity);
        $part->movements()->create(['type' => PartMovementType::Return, 'quantity' => $quantity, 'note' => $note, 'created_by' => $user->id]);
    }

    /**
     * A stocktake correction: sets the count directly and logs the (possibly negative) delta.
     */
    public function adjust(Part $part, int $newQuantity, User $user, string $note): void
    {
        $delta = $newQuantity - $part->quantity_on_hand;
        $part->update(['quantity_on_hand' => $newQuantity]);
        $part->movements()->create(['type' => PartMovementType::Adjustment, 'quantity' => $delta, 'note' => $note, 'created_by' => $user->id]);
    }
}

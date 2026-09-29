<?php

namespace App\Models;

use App\Enums\RepairWarrantyLevel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One repaired or replaced item Sang Y warrants after a repair, for its own number of months.
 */
#[Fillable(['rma_ticket_id', 'rma_quote_item_id', 'description', 'months'])]
class RmaWarrantyItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'months' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RmaTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(RmaTicket::class, 'rma_ticket_id');
    }

    /**
     * @return BelongsTo<RmaQuoteItem, $this>
     */
    public function quoteItem(): BelongsTo
    {
        return $this->belongsTo(RmaQuoteItem::class, 'rma_quote_item_id');
    }

    /**
     * Counted from the day the device goes back to the customer; null until then unless a date is given.
     */
    public function endsOn(?CarbonInterface $returnedOn = null): ?CarbonInterface
    {
        $from = $returnedOn ?? $this->ticket->returned_date;

        return $from?->copy()->addMonthsNoOverflow($this->months);
    }

    public function level(?CarbonInterface $today = null): RepairWarrantyLevel
    {
        $endsOn = $this->endsOn();

        if ($endsOn === null) {
            return RepairWarrantyLevel::None;
        }

        $daysLeft = $this->daysLeft($today);

        return match (true) {
            $daysLeft < 0 => RepairWarrantyLevel::Expired,
            $daysLeft <= config('rma.repair_warranty_expiring_days') => RepairWarrantyLevel::Expiring,
            default => RepairWarrantyLevel::Active,
        };
    }

    public function isActive(?CarbonInterface $today = null): bool
    {
        return $this->level($today)->isValid();
    }

    public function daysLeft(?CarbonInterface $today = null): ?int
    {
        $endsOn = $this->endsOn();

        return $endsOn === null ? null : (int) ($today ?? today())->copy()->startOfDay()->diffInDays($endsOn, false);
    }

    public function statusLabel(?CarbonInterface $today = null): string
    {
        return match ($this->level($today)) {
            RepairWarrantyLevel::Active => 'Còn '.$this->daysLeft($today).' ngày',
            RepairWarrantyLevel::Expiring => 'Sắp hết · còn '.$this->daysLeft($today).' ngày',
            RepairWarrantyLevel::Expired => 'Hết hạn '.$this->endsOn()->format('d/m/Y'),
            RepairWarrantyLevel::None => 'Tính từ ngày trả máy',
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rma_ticket_id', 'description', 'quantity', 'unit_price'])]
class RmaQuoteItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RmaTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(RmaTicket::class, 'rma_ticket_id');
    }

    public function lineTotal(): int
    {
        return $this->quantity * $this->unit_price;
    }
}

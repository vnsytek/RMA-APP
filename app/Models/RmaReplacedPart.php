<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rma_ticket_id', 'rma_center_shipment_id', 'part_code', 'description', 'quantity'])]
class RmaReplacedPart extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
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
     * @return BelongsTo<RmaCenterShipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(RmaCenterShipment::class, 'rma_center_shipment_id');
    }
}

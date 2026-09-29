<?php

namespace App\Models;

use App\Enums\ShipmentOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rma_ticket_id', 'service_center_id', 'vendor_case_no', 'appointment_date', 'sent_date', 'back_date',
    'center_return_no', 'outcome', 'note', 'created_by',
])]
class RmaCenterShipment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => ShipmentOutcome::class,
            'appointment_date' => 'date',
            'sent_date' => 'date',
            'back_date' => 'date',
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
     * @return BelongsTo<ServiceCenter, $this>
     */
    public function serviceCenter(): BelongsTo
    {
        return $this->belongsTo(ServiceCenter::class);
    }
}

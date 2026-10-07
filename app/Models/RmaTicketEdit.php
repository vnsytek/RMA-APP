<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rma_ticket_id', 'user_id', 'field', 'old_value', 'new_value', 'note'])]
class RmaTicketEdit extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<RmaTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(RmaTicket::class, 'rma_ticket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

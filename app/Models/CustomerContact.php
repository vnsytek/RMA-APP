<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person at a customer who sends devices in (e.g. "Chị Nga" at Pegatron).
 * Tickets copy the name and phone, so later edits here never change an old ticket.
 */
#[Fillable(['customer_id', 'name', 'phone', 'is_active'])]
class CustomerContact extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<RmaTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(RmaTicket::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        return collect([$this->name, $this->phone])->filter()->implode(' · ');
    }
}

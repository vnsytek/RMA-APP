<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company or person who sends devices in. Its people are in {@see CustomerContact};
 * `phone` is only the company's general number.
 */
#[Fillable(['name', 'tax_code', 'phone', 'address', 'note'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @return HasMany<RmaTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(RmaTicket::class);
    }

    /**
     * @return HasMany<CustomerContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class)->orderByDesc('is_active')->orderBy('name');
    }
}

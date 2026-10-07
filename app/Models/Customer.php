<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'contact_name', 'phone', 'address', 'shipping_address', 'note'])]
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
     * Where to print on receipt/return slips: the dedicated shipping address if one was given, else the registered address.
     */
    public function shippingAddress(): ?string
    {
        return $this->shipping_address ?: $this->address;
    }
}

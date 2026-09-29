<?php

namespace App\Models;

use Database\Factories\ServiceCenterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'brands', 'phone', 'address', 'is_active'])]
class ServiceCenter extends Model
{
    /** @use HasFactory<ServiceCenterFactory> */
    use HasFactory;

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
     * @return HasMany<RmaCenterShipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(RmaCenterShipment::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

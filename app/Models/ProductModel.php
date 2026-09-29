<?php

namespace App\Models;

use Database\Factories\ProductModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['brand_id', 'device_type_id', 'code', 'name', 'is_active'])]
class ProductModel extends Model
{
    /** @use HasFactory<ProductModelFactory> */
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
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<DeviceType, $this>
     */
    public function deviceType(): BelongsTo
    {
        return $this->belongsTo(DeviceType::class);
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * e.g. "Máy tính xách tay ASUS P500MV".
     */
    public function displayName(): string
    {
        return trim("{$this->deviceType->name} {$this->brand->name} {$this->code}");
    }

    /**
     * e.g. "ASUS P500MV".
     */
    public function shortName(): string
    {
        return "{$this->brand->name} {$this->code}";
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

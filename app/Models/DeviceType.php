<?php

namespace App\Models;

use Database\Factories\DeviceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'warranty_exclusions', 'is_active'])]
class DeviceType extends Model
{
    /** @use HasFactory<DeviceTypeFactory> */
    use HasFactory;

    /**
     * A new type starts with the configured "không bảo hành" list for its code, when there is one.
     */
    protected static function booted(): void
    {
        static::creating(function (DeviceType $type): void {
            $type->warranty_exclusions ??= config('rma.warranty_exclusions.'.$type->code);
        });
    }

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
     * @return HasMany<ProductModel, $this>
     */
    public function productModels(): HasMany
    {
        return $this->hasMany(ProductModel::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

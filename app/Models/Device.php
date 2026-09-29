<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['product_model_id', 'serial_number', 'replaced_from_device_id'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ProductModel, $this>
     */
    public function productModel(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class);
    }

    /**
     * The original device, when this one was handed out by the vendor as a replacement.
     *
     * @return BelongsTo<Device, $this>
     */
    public function replacedFrom(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'replaced_from_device_id');
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function replacements(): HasMany
    {
        return $this->hasMany(Device::class, 'replaced_from_device_id');
    }

    /**
     * Tickets opened for this device.
     *
     * @return HasMany<RmaTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(RmaTicket::class);
    }

    /**
     * Ids of this device and every device it replaced (a swapped device keeps the old one's history).
     *
     * @return Collection<int, int>
     */
    public function lineageIds(): Collection
    {
        $ids = collect([$this->id]);
        $parentId = $this->replaced_from_device_id;

        while ($parentId !== null && ! $ids->contains($parentId)) {
            $ids->push($parentId);
            $parentId = Device::query()->whereKey($parentId)->value('replaced_from_device_id');
        }

        return $ids;
    }

    public function displayName(): string
    {
        return $this->productModel->displayName();
    }
}

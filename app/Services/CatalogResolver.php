<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\DeviceType;
use App\Models\ProductModel;
use Illuminate\Support\Str;

/**
 * Turns the ticket form's "choose an existing entry or type a new one" fields into catalog rows.
 * A typed name that already exists (ignoring case) reuses the existing row instead of duplicating it.
 */
class CatalogResolver
{
    public function deviceType(string|int $value, ?string $newName): DeviceType
    {
        if ($value !== 'new') {
            return DeviceType::findOrFail($value);
        }

        $name = Str::squish((string) $newName);

        return $this->findDeviceTypeByName($name)
            ?? DeviceType::create(['code' => $this->uniqueTypeCode($name), 'name' => $name]);
    }

    public function brand(string|int $value, ?string $newName): Brand
    {
        if ($value !== 'new') {
            return Brand::findOrFail($value);
        }

        return Brand::firstOrCreate(['name' => $this->brandName((string) $newName)]);
    }

    public function productModel(string|int $value, ?string $newCode, DeviceType $type, Brand $brand): ProductModel
    {
        if ($value !== 'new') {
            return ProductModel::findOrFail($value);
        }

        $code = Str::squish((string) $newCode);

        return ProductModel::query()
            ->where('brand_id', $brand->id)
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
            ->first()
            ?? ProductModel::create(['brand_id' => $brand->id, 'device_type_id' => $type->id, 'code' => $code]);
    }

    public function findDeviceTypeByName(string $name): ?DeviceType
    {
        return DeviceType::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(Str::squish($name))])->first();
    }

    public function brandName(string $name): string
    {
        return mb_strtoupper(Str::squish($name));
    }

    /**
     * "Máy chiếu" → "MAY_CHIEU", with a numeric suffix when the code is taken.
     */
    public function uniqueTypeCode(string $name): string
    {
        $base = Str::of($name)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->limit(25, '')->toString() ?: 'TYPE';
        $code = $base;

        for ($suffix = 2; DeviceType::where('code', $code)->exists(); $suffix++) {
            $code = $base.'_'.$suffix;
        }

        return $code;
    }
}

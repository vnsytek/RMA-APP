<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\DeviceType;
use App\Models\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductModel>
 */
class ProductModelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'device_type_id' => DeviceType::factory(),
            'code' => strtoupper(fake()->unique()->bothify('??####')),
            'name' => null,
            'is_active' => true,
        ];
    }
}

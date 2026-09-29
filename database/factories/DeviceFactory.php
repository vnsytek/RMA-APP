<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_model_id' => ProductModel::factory(),
            'serial_number' => strtoupper(fake()->unique()->bothify('??########')),
            'replaced_from_device_id' => null,
        ];
    }
}

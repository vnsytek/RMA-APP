<?php

namespace Database\Factories;

use App\Models\DeviceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceType>
 */
class DeviceTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('TYPE_####'),
            'name' => 'Loại '.fake()->unique()->numberBetween(1, 99999),
            'is_active' => true,
        ];
    }
}

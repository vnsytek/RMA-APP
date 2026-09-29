<?php

namespace Database\Factories;

use App\Models\ServiceCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCenter>
 */
class ServiceCenterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'TTBH '.fake()->unique()->company(),
            'brands' => null,
            'phone' => fake()->numerify('0#########'),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }
}

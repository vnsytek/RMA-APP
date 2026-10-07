<?php

namespace Database\Factories;

use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PART-####')),
            'name' => fake()->words(3, true),
            'unit' => 'cái',
            'quantity_on_hand' => 0,
            'min_quantity' => null,
            'warranty_months' => null,
            'is_active' => true,
        ];
    }
}

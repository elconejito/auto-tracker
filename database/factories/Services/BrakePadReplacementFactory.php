<?php

namespace Database\Factories\Services;

use App\Models\Services\BrakePadReplacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrakePadReplacement>
 */
class BrakePadReplacementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position' => fake()->randomElement(['all', 'front', 'rear']),
            'manufacturer' => fake()->optional()->company(),
            'part_number' => fake()->optional()->bothify('PAD-####'),
        ];
    }
}

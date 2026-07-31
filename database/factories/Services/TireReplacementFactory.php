<?php

namespace Database\Factories\Services;

use App\Models\Services\TireReplacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TireReplacement>
 */
class TireReplacementFactory extends Factory
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
            'model' => fake()->optional()->word(),
            'size' => fake()->optional()->bothify('###/##R##'),
            'quantity' => fake()->numberBetween(1, 4),
        ];
    }
}

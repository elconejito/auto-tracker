<?php

namespace Database\Factories\Services;

use App\Models\Services\BrakeRotorReplacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrakeRotorReplacement>
 */
class BrakeRotorReplacementFactory extends Factory
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
            'part_number' => fake()->optional()->bothify('ROTOR-####'),
        ];
    }
}

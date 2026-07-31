<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nickname' => fake()->words(2, true),
            'year' => fake()->numberBetween(1990, now()->year + 1),
            'make' => fake()->randomElement(['Ford', 'Honda', 'Toyota']),
            'model' => fake()->randomElement(['Civic', 'F-150', 'Camry']),
            'trim' => fake()->optional()->word(),
            'vin' => fake()->optional()->bothify('?????????????????'),
            'license_plate' => fake()->optional()->bothify('???-####'),
            'current_odometer' => fake()->numberBetween(0, 300000),
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'archived_at' => now(),
        ]);
    }
}

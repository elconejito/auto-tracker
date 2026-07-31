<?php

namespace Database\Factories;

use App\Models\MaintenanceEvent;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceEvent>
 */
class MaintenanceEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'performed_at' => fake()->dateTimeBetween('-2 years'),
            'odometer' => fake()->numberBetween(0, 250000),
            'provider' => fake()->optional()->company(),
            'total_cost' => fake()->optional()->randomFloat(2, 0, 2500),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

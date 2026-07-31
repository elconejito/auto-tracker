<?php

namespace Database\Factories;

use App\Models\FuelEntry;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelEntry>
 */
class FuelEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'fueled_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'odometer' => fake()->numberBetween(1000, 300000),
            'gallons' => fake()->randomFloat(3, 5, 25),
            'total_cost' => fake()->optional()->randomFloat(2, 20, 150),
            'price_per_gallon' => null,
            'miles_driven' => null,
            'calculated_mpg' => null,
            'station' => fake()->optional()->company(),
            'fuel_grade' => fake()->optional()->randomElement(['Regular', 'Midgrade', 'Premium']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

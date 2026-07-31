<?php

namespace Database\Factories;

use App\Models\MaintenanceEvent;
use App\Models\ServiceLine;
use App\Models\Services\OilChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceLine>
 */
class ServiceLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'maintenance_event_id' => MaintenanceEvent::factory(),
            'serviceable_type' => 'oil_change',
            'serviceable_id' => OilChange::factory(),
            'line_cost' => fake()->optional()->randomFloat(2, 0, 500),
        ];
    }
}

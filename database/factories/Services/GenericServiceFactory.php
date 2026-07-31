<?php

namespace Database\Factories\Services;

use App\Models\Services\GenericService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenericService>
 */
class GenericServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
        ];
    }
}

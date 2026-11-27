<?php

namespace Tests\Feature\Api;

use App\Models\FuelEntry;
use App\Models\MaintenanceEvent;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginatedHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_equal_sort_values_have_stable_order_across_pages(): void
    {
        $user = User::factory()->create();
        $vehicles = Vehicle::factory()->count(2)->for($user)->create(['nickname' => 'Daily']);
        $vehicle = $vehicles->first();
        $fuel = FuelEntry::factory()->count(2)->for($vehicle)->create(['gallons' => 10]);
        $maintenance = MaintenanceEvent::factory()->count(2)->for($vehicle)->create(['performed_at' => '2026-01-01']);

        $this->actingAs($user, 'api');
        foreach ([
            ['/api/vehicles?sort=nickname', $vehicles->sortBy('id')],
            ["/api/vehicles/{$vehicle->id}/fuel-entries?sort=gallons", $fuel->sortByDesc('id')],
            ["/api/vehicles/{$vehicle->id}/maintenance-events?sort=-performed_at", $maintenance->sortByDesc('id')],
        ] as [$url, $records]) {
            foreach ($records->values() as $index => $record) {
                $this->getJson($url.'&page[size]=1&page[number]='.($index + 1))
                    ->assertOk()
                    ->assertJsonCount(1, 'data')
                    ->assertJsonPath('data.0.id', $record->id)
                    ->assertJsonPath('meta.pagination.total_pages', 2);
            }
        }
    }
}

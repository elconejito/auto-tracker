<?php

namespace Tests\Feature\Api;

use App\Models\FuelEntry;
use App\Models\MaintenanceEvent;
use App\Models\ServiceLine;
use App\Models\Services\OilChange;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_combines_fuel_and_maintenance_in_reverse_chronology(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();
        FuelEntry::factory()->for($vehicle)->create(['fueled_at' => '2026-01-01 10:00:00', 'odometer' => 1000, 'station' => 'Fuel Stop']);
        $event = MaintenanceEvent::factory()->for($vehicle)->create(['performed_at' => '2026-01-02 10:00:00', 'odometer' => 1100, 'provider' => 'Garage']);
        $oilChange = OilChange::factory()->create();
        ServiceLine::factory()->for($event)->create([
            'serviceable_type' => 'oil_change',
            'serviceable_id' => $oilChange->getKey(),
        ]);

        $this->actingAs($user, 'api')->getJson("/api/vehicles/{$vehicle->getKey()}/timeline?page[size]=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.kind', 'maintenance')
            ->assertJsonPath('data.0.attributes.details.services.0.type', 'oil_change')
            ->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_timeline_is_owner_scoped_and_page_size_is_limited(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'api')->getJson("/api/vehicles/{$vehicle->getKey()}/timeline")->assertNotFound();
        $this->actingAs($owner, 'api')->getJson("/api/vehicles/{$vehicle->getKey()}/timeline?page[size]=101")
            ->assertUnprocessable();
    }

    public function test_dashboard_returns_summaries_recency_and_recent_timeline(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create(['current_odometer' => 2000]);
        FuelEntry::factory()->for($vehicle)->create([
            'fueled_at' => '2026-01-01 10:00:00',
            'odometer' => 1500,
            'calculated_mpg' => '25.00',
        ]);
        FuelEntry::factory()->for($vehicle)->create([
            'fueled_at' => '2026-01-03 10:00:00',
            'odometer' => 1800,
            'calculated_mpg' => '30.00',
        ]);
        $event = MaintenanceEvent::factory()->for($vehicle)->create([
            'performed_at' => '2026-01-02 10:00:00',
            'odometer' => 1700,
        ]);
        $oilChange = OilChange::factory()->create();
        ServiceLine::factory()->for($event)->create([
            'serviceable_type' => 'oil_change',
            'serviceable_id' => $oilChange->getKey(),
        ]);

        $this->actingAs($user, 'api')->getJson("/api/vehicles/{$vehicle->getKey()}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.type', 'vehicle_dashboards')
            ->assertJsonPath('data.attributes.vehicle.current_odometer', 2000)
            ->assertJsonPath('data.attributes.fuel_summary.latest_mpg', '30.00')
            ->assertJsonPath('data.attributes.fuel_summary.average_mpg', '27.50')
            ->assertJsonPath('data.attributes.maintenance_summary.most_recent_odometer', 1700)
            ->assertJsonFragment(['key' => 'oil_change', 'miles_since' => 300])
            ->assertJsonCount(3, 'data.attributes.recent_timeline');
    }
}

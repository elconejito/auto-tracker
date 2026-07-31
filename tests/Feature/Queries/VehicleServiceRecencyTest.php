<?php

namespace Tests\Feature\Queries;

use App\Actions\Maintenance\ServiceCatalog;
use App\Models\MaintenanceEvent;
use App\Models\ServiceLine;
use App\Models\Vehicle;
use App\Queries\VehicleServiceRecency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleServiceRecencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_history_returns_stable_unavailable_records(): void
    {
        $vehicle = Vehicle::factory()->create(['current_odometer' => 10000]);

        $recency = app(VehicleServiceRecency::class)->forVehicle($vehicle);

        $this->assertCount(17, $recency);
        $this->assertNotContains('generic_service', array_column($recency, 'key'));
        $this->assertSame([
            'key', 'type', 'label', 'applicability', 'performed_at', 'odometer', 'miles_since',
        ], array_keys($recency[0]));

        foreach ($recency as $record) {
            $this->assertNull($record['performed_at']);
            $this->assertNull($record['odometer']);
            $this->assertNull($record['miles_since']);
        }
    }

    public function test_brake_recency_keeps_front_and_rear_records_and_prefers_newer_axle_work(): void
    {
        $vehicle = Vehicle::factory()->create(['current_odometer' => 10000]);
        $all = $this->record($vehicle, 'brake_pad_replacement', ['position' => 'all'], '2026-01-01 10:00:00', 7000);
        $front = $this->record($vehicle, 'brake_pad_replacement', ['position' => 'front'], '2026-02-01 10:00:00', 8000);

        $records = collect(app(VehicleServiceRecency::class)->forVehicle($vehicle));

        $this->assertSame('brake_pad_replacement', $records->firstWhere('key', 'brake_pad_replacement_front')['type']);
        $this->assertSame('front', $records->firstWhere('key', 'brake_pad_replacement_front')['applicability']);
        $this->assertSame($front->performed_at->toAtomString(), $records->firstWhere('key', 'brake_pad_replacement_front')['performed_at']);
        $this->assertSame(2000, $records->firstWhere('key', 'brake_pad_replacement_front')['miles_since']);
        $this->assertSame($all->performed_at->toAtomString(), $records->firstWhere('key', 'brake_pad_replacement_rear')['performed_at']);
        $this->assertSame(3000, $records->firstWhere('key', 'brake_pad_replacement_rear')['miles_since']);
    }

    public function test_rotors_are_independent_from_brake_pads(): void
    {
        $vehicle = Vehicle::factory()->create(['current_odometer' => 5000]);
        $front = $this->record($vehicle, 'brake_rotor_replacement', ['position' => 'front'], '2026-03-01 10:00:00', 4200);
        $rear = $this->record($vehicle, 'brake_rotor_replacement', ['position' => 'rear'], '2026-04-01 10:00:00', 4300);

        $records = collect(app(VehicleServiceRecency::class)->forVehicle($vehicle));

        $this->assertSame($front->performed_at->toAtomString(), $records->firstWhere('key', 'brake_rotor_replacement_front')['performed_at']);
        $this->assertSame($rear->performed_at->toAtomString(), $records->firstWhere('key', 'brake_rotor_replacement_rear')['performed_at']);
        $this->assertNull($records->firstWhere('key', 'brake_pad_replacement_front')['performed_at']);
    }

    public function test_tire_replacement_uses_axle_applicability_and_rotation_is_whole_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['current_odometer' => 12000]);
        $all = $this->record($vehicle, 'tire_replacement', ['position' => 'all'], '2026-01-01 10:00:00', 9000);
        $rear = $this->record($vehicle, 'tire_replacement', ['position' => 'rear_left'], '2026-02-01 10:00:00', 10000);
        $rotation = $this->record($vehicle, 'tire_rotation', [], '2026-03-01 10:00:00', 11000);

        $records = collect(app(VehicleServiceRecency::class)->forVehicle($vehicle));

        $this->assertSame($all->performed_at->toAtomString(), $records->firstWhere('key', 'tire_replacement_front')['performed_at']);
        $this->assertSame($rear->performed_at->toAtomString(), $records->firstWhere('key', 'tire_replacement_rear')['performed_at']);
        $this->assertSame(2000, $records->firstWhere('key', 'tire_replacement_rear')['miles_since']);
        $this->assertSame('all', $records->firstWhere('key', 'tire_rotation')['applicability']);
        $this->assertSame($rotation->performed_at->toAtomString(), $records->firstWhere('key', 'tire_rotation')['performed_at']);
        $this->assertSame(1000, $records->firstWhere('key', 'tire_rotation')['miles_since']);
    }

    public function test_latest_service_uses_performed_at_then_event_id(): void
    {
        $vehicle = Vehicle::factory()->create(['current_odometer' => 9000]);
        $older = $this->record($vehicle, 'oil_change', [], '2026-01-01 10:00:00', 7000);
        $sameDateFirst = $this->record($vehicle, 'oil_change', [], '2026-02-01 10:00:00', 7100);
        $sameDateSecond = $this->record($vehicle, 'oil_change', [], '2026-02-01 10:00:00', 7200);

        $latestSameDate = strcmp((string) $sameDateFirst->getKey(), (string) $sameDateSecond->getKey()) > 0
            ? $sameDateFirst
            : $sameDateSecond;
        $record = collect(app(VehicleServiceRecency::class)->forVehicle($vehicle))->firstWhere('key', 'oil_change');

        $this->assertNotSame($older->performed_at->toAtomString(), $record['performed_at']);
        $this->assertSame($latestSameDate->getKey(), $record['odometer'] === $sameDateFirst->odometer ? $sameDateFirst->getKey() : $sameDateSecond->getKey());
        $this->assertSame(9000 - $latestSameDate->odometer, $record['miles_since']);
    }

    public function test_generic_service_is_not_mileage_aware(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->record($vehicle, 'generic_service', ['name' => 'Alignment'], '2026-01-01 10:00:00', 1000);

        $genericServiceClass = ServiceCatalog::modelFor('generic_service');

        $this->assertFalse($genericServiceClass::factory()->make()->supportsMileageSince());
        $this->assertNotContains('generic_service', array_column(app(VehicleServiceRecency::class)->forVehicle($vehicle), 'type'));
    }

    /** @param array<string, mixed> $attributes */
    private function record(Vehicle $vehicle, string $type, array $attributes, string $performedAt, int $odometer): MaintenanceEvent
    {
        $event = MaintenanceEvent::factory()->for($vehicle)->create([
            'performed_at' => $performedAt,
            'odometer' => $odometer,
        ]);
        $serviceClass = ServiceCatalog::modelFor($type);
        $service = $serviceClass::factory()->create($attributes);

        ServiceLine::factory()->create([
            'maintenance_event_id' => $event->getKey(),
            'serviceable_type' => $type,
            'serviceable_id' => $service->getKey(),
        ]);

        return $event;
    }
}

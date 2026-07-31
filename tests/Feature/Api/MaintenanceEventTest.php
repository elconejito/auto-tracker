<?php

namespace Tests\Feature\Api;

use App\Models\MaintenanceEvent;
use App\Models\ServiceLine;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MaintenanceEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_visit_persists_and_transforms_every_concrete_service_type(): void
    {
        [$user, $vehicle] = $this->ownedVehicle();
        $services = collect([
            'oil_change', 'tire_replacement', 'tire_rotation', 'brake_pad_replacement',
            'brake_rotor_replacement', 'timing_belt_replacement', 'serpentine_belt_replacement',
            'battery_replacement', 'air_filter_replacement', 'cabin_air_filter_replacement',
            'spark_plug_replacement', 'transmission_service', 'coolant_service',
            'vehicle_inspection', 'generic_service',
        ])->map(fn (string $type): array => [
            'type' => $type,
            'line_cost' => '10.00',
            'details' => match ($type) {
                'generic_service' => ['name' => 'Alignment', 'description' => 'Four-wheel alignment'],
                'tire_replacement', 'brake_pad_replacement', 'brake_rotor_replacement' => ['position' => 'front'],
                'oil_change' => ['viscosity' => '5W-30', 'quantity' => 5, 'filter_replaced' => true],
                default => [],
            },
        ])->all();

        $response = $this->actingAs($user, 'api')->withCsrf()->postJson(
            "/api/vehicles/{$vehicle->getKey()}/maintenance-events",
            $this->eventData(['services' => $services]),
        );

        $response->assertCreated()->assertJsonCount(15, 'data.attributes.services');
        $event = MaintenanceEvent::query()->with('serviceLines.serviceable')->sole();
        $this->assertCount(15, $event->serviceLines);
        $this->assertEqualsCanonicalizing(array_column($services, 'type'), $event->serviceLines->pluck('serviceable_type')->all());
        $this->assertSame('Alignment', $event->serviceLines->firstWhere('serviceable_type', 'generic_service')->serviceable->name);
        $this->assertSame('oil_change', $response->json('data.attributes.services.0.type'));
    }

    public function test_editing_replaces_service_lines_transactionally(): void
    {
        [$user, $vehicle] = $this->ownedVehicle();
        $event = $this->createEvent($user, $vehicle);
        $oldServiceId = $event->serviceLines()->firstOrFail()->serviceable_id;

        $this->actingAs($user, 'api')->withCsrf()->patchJson(
            "/api/maintenance-events/{$event->getKey()}",
            ['services' => [['type' => 'generic_service', 'line_cost' => null, 'details' => ['name' => 'Custom fabrication']]]],
        )->assertOk()->assertJsonPath('data.attributes.services.0.type', 'generic_service');

        $this->assertDatabaseMissing('oil_changes', ['id' => $oldServiceId]);
        $this->assertSame('generic_service', ServiceLine::query()->sole()->serviceable_type);
    }

    public function test_event_requires_a_service_and_generic_work_requires_a_name(): void
    {
        [$user, $vehicle] = $this->ownedVehicle();

        $this->actingAs($user, 'api')->withCsrf()->postJson(
            "/api/vehicles/{$vehicle->getKey()}/maintenance-events",
            $this->eventData(['services' => []]),
        )->assertUnprocessable()->assertJsonPath('errors.0.source.pointer', '/data/attributes/services');

        $this->actingAs($user, 'api')->withCsrf()->postJson(
            "/api/vehicles/{$vehicle->getKey()}/maintenance-events",
            $this->eventData(['services' => [['type' => 'generic_service', 'details' => []]]]),
        )->assertUnprocessable()->assertJsonPath('errors.0.source.pointer', '/data/attributes/services/0/details/name');
    }

    public function test_owner_scoping_and_archived_vehicle_rules_apply(): void
    {
        [$owner, $vehicle] = $this->ownedVehicle();
        $event = $this->createEvent($owner, $vehicle);
        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'api')->getJson("/api/maintenance-events/{$event->getKey()}")->assertNotFound();
        $vehicle->forceFill(['archived_at' => now()])->save();
        $this->actingAs($owner, 'api')->getJson("/api/maintenance-events/{$event->getKey()}")->assertOk();
        $this->actingAs($owner, 'api')->withCsrf()->patchJson("/api/maintenance-events/{$event->getKey()}", ['notes' => 'No'])->assertForbidden();
    }

    private function createEvent(User $user, Vehicle $vehicle): MaintenanceEvent
    {
        $response = $this->actingAs($user, 'api')->withCsrf()->postJson(
            "/api/vehicles/{$vehicle->getKey()}/maintenance-events",
            $this->eventData(),
        )->assertCreated();

        return MaintenanceEvent::query()->with('serviceLines')->findOrFail($response->json('data.id'));
    }

    /** @return array{0: User, 1: Vehicle} */
    private function ownedVehicle(): array
    {
        $user = User::factory()->create();

        return [$user, Vehicle::factory()->for($user)->create(['current_odometer' => 1000])];
    }

    /** @return array<string, mixed> */
    private function eventData(array $overrides = []): array
    {
        return array_merge([
            'performed_at' => '2026-07-30 10:00:00',
            'odometer' => 1200,
            'provider' => 'Trusted Garage',
            'total_cost' => '50.00',
            'services' => [[
                'type' => 'oil_change',
                'line_cost' => '50.00',
                'details' => ['viscosity' => '5W-30', 'filter_replaced' => true],
            ]],
        ], $overrides);
    }

    private function withCsrf(): static
    {
        $token = Str::random(64);

        return $this->withCredentials()
            ->withUnencryptedCookie(config('jwt.csrf.cookie_name'), $token)
            ->withHeader(config('jwt.csrf.header_name'), $token);
    }
}

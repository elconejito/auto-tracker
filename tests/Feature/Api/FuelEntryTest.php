<?php

namespace Tests\Feature\Api;

use App\Models\FuelEntry;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FuelEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_entry_has_null_calculations_and_cost_derivation(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create(['current_odometer' => 900]);

        $response = $this->actingAs($user, 'api')->withCsrf()->postJson(
            "/api/vehicles/{$vehicle->getKey()}/fuel-entries",
            $this->fuelData(['odometer' => 1000, 'gallons' => '10.000', 'total_cost' => '35.00']),
        );

        $response->assertCreated()
            ->assertJsonPath('data.type', 'fuel_entries')
            ->assertJsonPath('data.attributes.gallons', '10.000')
            ->assertJsonPath('data.attributes.total_cost', '35.00')
            ->assertJsonPath('data.attributes.price_per_gallon', '3.500')
            ->assertJsonPath('data.relationships.vehicle.data.id', (string) $vehicle->getKey())
            ->assertJsonPath('data.attributes.miles_driven', null)
            ->assertJsonPath('data.attributes.calculated_mpg', null);
    }

    public function test_consecutive_entries_calculate_miles_and_mpg(): void
    {
        [$user, $vehicle] = $this->ownedVehicle();

        $this->createEntry($user, $vehicle, ['fueled_at' => '2026-01-01 10:00:00', 'odometer' => 1000]);
        $second = $this->createEntry($user, $vehicle, [
            'fueled_at' => '2026-01-02 10:00:00',
            'odometer' => 1300,
            'gallons' => '10.000',
        ]);

        $this->assertSame(300, $second->miles_driven);
        $this->assertSame('30.00', $second->calculated_mpg);
    }

    public function test_historical_insert_update_and_delete_reconnect_later_entries(): void
    {
        [$user, $vehicle] = $this->ownedVehicle();

        $this->createEntry($user, $vehicle, ['fueled_at' => '2026-01-01 10:00:00', 'odometer' => 1000]);
        $middle = $this->createEntry($user, $vehicle, ['fueled_at' => '2026-01-03 10:00:00', 'odometer' => 1300]);
        $later = $this->createEntry($user, $vehicle, ['fueled_at' => '2026-01-04 10:00:00', 'odometer' => 1600]);
        $historical = $this->createEntry($user, $vehicle, [
            'fueled_at' => '2026-01-02 10:00:00',
            'odometer' => 1150,
            'gallons' => '5.000',
        ]);

        $this->assertSame(150, $middle->fresh()->miles_driven);
        $this->assertSame(300, $later->fresh()->miles_driven);

        $this->actingAs($user, 'api')->withCsrf()->patchJson(
            "/api/fuel-entries/{$historical->getKey()}", ['odometer' => 1200],
        )->assertOk();

        $this->assertSame(100, $middle->fresh()->miles_driven);

        $this->actingAs($user, 'api')->withCsrf()->deleteJson(
            "/api/fuel-entries/{$historical->getKey()}",
        )->assertNoContent();

        $this->assertSame(300, $middle->fresh()->miles_driven);
        $this->assertSame(300, $later->fresh()->miles_driven);
    }

    public function test_invalid_chronology_rolls_back(): void
    {
        [$user, $vehicle] = $this->ownedVehicle();
        $this->createEntry($user, $vehicle, ['odometer' => 1000]);

        $this->actingAs($user, 'api')->withCsrf()->postJson(
            "/api/vehicles/{$vehicle->getKey()}/fuel-entries",
            $this->fuelData(['odometer' => 900]),
        )->assertUnprocessable()->assertJsonPath('errors.0.code', 'invalid_fuel_chronology');

        $this->assertSame(1, FuelEntry::query()->whereBelongsTo($vehicle)->count());
    }

    public function test_moving_an_entry_reorders_before_chronology_validation(): void
    {
        [$user, $vehicle] = $this->ownedVehicle();
        $this->createEntry($user, $vehicle, ['fueled_at' => '2026-01-01 10:00:00', 'odometer' => 1000]);
        $moving = $this->createEntry($user, $vehicle, ['fueled_at' => '2026-01-02 10:00:00', 'odometer' => 1200]);
        $this->createEntry($user, $vehicle, ['fueled_at' => '2026-01-03 10:00:00', 'odometer' => 1400]);

        $this->actingAs($user, 'api')->withCsrf()->patchJson(
            "/api/fuel-entries/{$moving->getKey()}",
            ['fueled_at' => '2026-01-04 10:00:00'],
        )->assertUnprocessable()->assertJsonPath('errors.0.code', 'invalid_fuel_chronology');

        $this->assertSame('2026-01-02 10:00:00', $moving->fresh()->fueled_at->format('Y-m-d H:i:s'));
    }

    public function test_cost_derivation_price_only_and_one_cent_tolerance(): void
    {
        [$user, $vehicle] = $this->ownedVehicle();
        $priceOnly = $this->createEntry($user, $vehicle, ['odometer' => 1000, 'price_per_gallon' => '3.501']);
        $this->assertSame('35.01', $priceOnly->total_cost);

        $withinTolerance = $this->createEntry($user, $vehicle, [
            'odometer' => 1100,
            'total_cost' => '35.01',
            'price_per_gallon' => '3.500',
        ]);
        $this->assertSame('35.01', $withinTolerance->total_cost);

        $this->actingAs($user, 'api')->withCsrf()->postJson(
            "/api/vehicles/{$vehicle->getKey()}/fuel-entries",
            $this->fuelData(['odometer' => 1200, 'total_cost' => '35.02', 'price_per_gallon' => '3.500']),
        )->assertUnprocessable()->assertJsonPath('errors.0.code', 'invalid_fuel_cost');
    }

    public function test_equal_timestamps_use_ulid_tie_breaker(): void
    {
        [$user, $vehicle] = $this->ownedVehicle();
        $timestamp = '2026-02-01 10:00:00';
        $this->createEntry($user, $vehicle, ['fueled_at' => $timestamp, 'odometer' => 1000]);
        $this->createEntry($user, $vehicle, ['fueled_at' => $timestamp, 'odometer' => 1100]);

        $entries = FuelEntry::query()->forVehicle($vehicle)->chronological()->get();

        $this->assertCount(2, $entries);
        $this->assertLessThan($entries[1]->odometer, $entries[0]->odometer);
        $this->assertSame(100, $entries[1]->miles_driven);
    }

    public function test_owner_scoping_covers_nested_and_direct_endpoints(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();
        $entry = $this->createEntry($owner, $vehicle);

        $this->actingAs($intruder, 'api')->getJson("/api/vehicles/{$vehicle->getKey()}/fuel-entries")->assertNotFound();
        $this->actingAs($intruder, 'api')->getJson("/api/fuel-entries/{$entry->getKey()}")->assertNotFound();
        $this->actingAs($intruder, 'api')->withCsrf()->patchJson("/api/fuel-entries/{$entry->getKey()}", ['odometer' => 1100])->assertNotFound();
        $this->actingAs($intruder, 'api')->withCsrf()->deleteJson("/api/fuel-entries/{$entry->getKey()}")->assertNotFound();
    }

    public function test_archived_vehicle_allows_reads_but_rejects_mutations(): void
    {
        [$user, $vehicle] = $this->ownedVehicle();
        $entry = $this->createEntry($user, $vehicle);
        $vehicle->forceFill(['archived_at' => now()])->save();

        $this->actingAs($user, 'api')->getJson("/api/vehicles/{$vehicle->getKey()}/fuel-entries")->assertOk();
        $this->actingAs($user, 'api')->getJson("/api/fuel-entries/{$entry->getKey()}")->assertOk();
        $this->actingAs($user, 'api')->withCsrf()->postJson("/api/vehicles/{$vehicle->getKey()}/fuel-entries", $this->fuelData(['odometer' => 1100]))->assertForbidden();
        $this->actingAs($user, 'api')->withCsrf()->patchJson("/api/fuel-entries/{$entry->getKey()}", ['odometer' => 1100])->assertForbidden();
        $this->actingAs($user, 'api')->withCsrf()->deleteJson("/api/fuel-entries/{$entry->getKey()}")->assertForbidden();
    }

    public function test_collection_filters_sorts_and_limits_pagination(): void
    {
        [$user, $vehicle] = $this->ownedVehicle();
        $this->createEntry($user, $vehicle, ['fueled_at' => '2026-01-01 10:00:00', 'odometer' => 1000]);
        $this->createEntry($user, $vehicle, ['fueled_at' => '2026-01-02 10:00:00', 'odometer' => 1100]);
        $this->createEntry($user, $vehicle, ['fueled_at' => '2026-01-03 10:00:00', 'odometer' => 1200]);

        $this->actingAs($user, 'api')->getJson(
            "/api/vehicles/{$vehicle->getKey()}/fuel-entries?filter[odometer_min]=1100&sort=odometer&page[size]=1",
        )->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.attributes.odometer', 1100)->assertJsonPath('meta.pagination.per_page', 1);

        $this->actingAs($user, 'api')->getJson(
            "/api/vehicles/{$vehicle->getKey()}/fuel-entries?sort=unknown",
        )->assertBadRequest();
    }

    public function test_current_odometer_never_decreases_during_synchronization(): void
    {
        [$user, $vehicle] = $this->ownedVehicle(['current_odometer' => 5000]);
        $this->createEntry($user, $vehicle, ['odometer' => 3000]);
        $this->assertSame(5000, $vehicle->fresh()->current_odometer);

        $this->createEntry($user, $vehicle, ['odometer' => 6000]);
        $this->assertSame(6000, $vehicle->fresh()->current_odometer);
    }

    public function test_vehicle_odometer_cannot_be_edited_below_latest_fuel_entry(): void
    {
        [$user, $vehicle] = $this->ownedVehicle();
        $this->createEntry($user, $vehicle, ['odometer' => 3000]);

        $this->actingAs($user, 'api')->withCsrf()->patchJson(
            "/api/vehicles/{$vehicle->getKey()}",
            ['current_odometer' => 2999],
        )->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/current_odometer');
    }

    public function test_vehicle_summary_scope_returns_available_fuel_metrics(): void
    {
        [$user, $vehicle] = $this->ownedVehicle();
        $this->createEntry($user, $vehicle, ['fueled_at' => '2026-01-01 10:00:00', 'odometer' => 1000]);
        $this->createEntry($user, $vehicle, ['fueled_at' => '2026-01-02 10:00:00', 'odometer' => 1100]);
        $this->createEntry($user, $vehicle, ['fueled_at' => '2026-01-03 10:00:00', 'odometer' => 1250]);

        $summary = Vehicle::query()->withFuelSummaries()->findOrFail($vehicle->getKey());

        $this->assertSame('15.00', $summary->latest_mpg);
        $this->assertSame('12.50', $summary->average_mpg);
        $this->assertSame('2026-01-03T10:00:00+00:00', $summary->most_recent_fueled_at->toAtomString());
    }

    /** @return array{0: User, 1: Vehicle} */
    private function ownedVehicle(array $attributes = []): array
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create($attributes);

        return [$user, $vehicle];
    }

    private function createEntry(User $user, Vehicle $vehicle, array $overrides = []): FuelEntry
    {
        $response = $this->actingAs($user, 'api')->withCsrf()->postJson(
            "/api/vehicles/{$vehicle->getKey()}/fuel-entries",
            $this->fuelData($overrides),
        );

        $response->assertCreated();

        return FuelEntry::query()->findOrFail($response->json('data.id'));
    }

    /** @return array<string, mixed> */
    private function fuelData(array $overrides = []): array
    {
        return array_merge([
            'fueled_at' => '2026-01-01 10:00:00',
            'odometer' => 1000,
            'gallons' => '10.000',
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

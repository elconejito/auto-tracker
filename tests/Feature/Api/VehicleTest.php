<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_view_a_vehicle_with_a_ulid(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->withCsrf()
            ->postJson('/api/vehicles', $this->validVehicleData());

        $response->assertCreated()
            ->assertJsonPath('data.type', 'vehicles')
            ->assertJsonPath('data.attributes.nickname', 'Daily Driver');

        $vehicle = Vehicle::query()->firstOrFail();

        $this->assertTrue(Str::isUlid((string) $vehicle->getKey()));
        $this->assertTrue($vehicle->user->is($user));

        $this->actingAs($user, 'api')
            ->getJson('/api/vehicles/'.$vehicle->getKey())
            ->assertOk()
            ->assertJsonPath('data.id', (string) $vehicle->getKey());
    }

    public function test_vehicle_input_is_validated(): void
    {
        $this->actingAs(User::factory()->create(), 'api')
            ->withCsrf()
            ->postJson('/api/vehicles', [
                'nickname' => '',
                'year' => 1800,
                'current_odometer' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'validation_failed');
    }

    public function test_user_cannot_infer_another_users_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'api')
            ->getJson('/api/vehicles/'.$vehicle->getKey())
            ->assertNotFound();
    }

    public function test_user_can_update_an_active_vehicle_but_not_an_archived_vehicle(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();

        $this->actingAs($user, 'api')
            ->withCsrf()
            ->patchJson('/api/vehicles/'.$vehicle->getKey(), ['nickname' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.attributes.nickname', 'Updated');

        $vehicle->forceFill(['archived_at' => now()])->save();

        $this->actingAs($user, 'api')
            ->withCsrf()
            ->patchJson('/api/vehicles/'.$vehicle->getKey(), ['nickname' => 'Forbidden'])
            ->assertForbidden();
    }

    public function test_user_can_archive_and_restore_a_vehicle(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();

        $this->actingAs($user, 'api')
            ->withCsrf()
            ->postJson('/api/vehicles/'.$vehicle->getKey().'/archive')
            ->assertOk();

        $this->assertNotNull($vehicle->fresh()->archived_at);

        $this->actingAs($user, 'api')
            ->withCsrf()
            ->postJson('/api/vehicles/'.$vehicle->getKey().'/restore')
            ->assertOk()
            ->assertJsonPath('data.attributes.archived_at', null);
    }

    public function test_user_cannot_archive_or_restore_another_users_vehicle(): void
    {
        $intruder = User::factory()->create();
        $activeVehicle = Vehicle::factory()->create();
        $archivedVehicle = Vehicle::factory()->archived()->create();

        $this->actingAs($intruder, 'api')
            ->withCsrf()
            ->postJson('/api/vehicles/'.$activeVehicle->getKey().'/archive')
            ->assertNotFound();

        $this->actingAs($intruder, 'api')
            ->withCsrf()
            ->postJson('/api/vehicles/'.$archivedVehicle->getKey().'/restore')
            ->assertNotFound();
    }

    public function test_collection_filters_sorts_and_paginates_owned_vehicles(): void
    {
        $user = User::factory()->create();
        Vehicle::factory()->for($user)->create(['nickname' => 'Zulu']);
        Vehicle::factory()->for($user)->create(['nickname' => 'Alpha']);
        Vehicle::factory()->for($user)->archived()->create(['nickname' => 'Archived']);
        Vehicle::factory()->create(['nickname' => 'Someone Else']);

        $this->actingAs($user, 'api')
            ->getJson('/api/vehicles?sort=nickname&page[size]=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.nickname', 'Alpha')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2);

        $this->actingAs($user, 'api')
            ->getJson('/api/vehicles?filter[archived]=only')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.nickname', 'Archived');
    }

    public function test_vehicle_permanent_deletion_is_not_routed(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();

        $this->actingAs($user, 'api')
            ->withCsrf()
            ->deleteJson('/api/vehicles/'.$vehicle->getKey())
            ->assertMethodNotAllowed();

        $this->assertModelExists($vehicle);
    }

    /**
     * @return array<string, mixed>
     */
    private function validVehicleData(): array
    {
        return [
            'nickname' => 'Daily Driver',
            'year' => 2022,
            'make' => 'Honda',
            'model' => 'Civic',
            'trim' => 'Sport',
            'vin' => '2HGFE2F50NH123456',
            'license_plate' => 'AUTO-1',
            'current_odometer' => 24500,
        ];
    }

    private function withCsrf(): static
    {
        $token = Str::random(64);

        return $this->withCredentials()
            ->withUnencryptedCookie(config('jwt.csrf.cookie_name'), $token)
            ->withHeader(config('jwt.csrf.header_name'), $token);
    }
}

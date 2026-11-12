<?php

namespace Tests\Feature\Api;

use App\Models\FuelEntry;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FuelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_uses_selected_vehicle_ignores_extra_columns_and_recalculates_existing_history(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create(['current_odometer' => 100]);
        $other = Vehicle::factory()->for($user)->create();
        $later = FuelEntry::factory()->for($vehicle)->create(['fueled_at' => '2025-03-01', 'odometer' => 1600, 'gallons' => '10.000']);
        $csv = "\xEF\xBB\xBFmodel,odometer,gallons,price,fuelup_date,notes\nLegacy model,1300,10,3.5,2025-02-01,ignored\n\nLegacy model,1000,10,3.5,2025-01-01,ignored\n";

        $this->actingAs($user, 'api')->withCsrf()->postJson("/api/vehicles/{$vehicle->id}/fuel-imports", [
            'file' => UploadedFile::fake()->createWithContent('history.csv', $csv),
        ])->assertOk()->assertJsonPath('data.status', 'success')->assertJsonPath('data.imported', 2)->assertJsonPath('data.failed', 0);

        $entries = $vehicle->fuelEntries()->chronological()->get();
        $this->assertCount(3, $entries);
        $this->assertSame('35.00', $entries[0]->total_cost);
        $this->assertSame('3.500', $entries[0]->price_per_gallon);
        $this->assertNull($entries[0]->notes);
        $this->assertNull($entries[0]->calculated_mpg);
        $this->assertSame('30.00', $entries[1]->calculated_mpg);
        $this->assertSame('30.00', $later->fresh()->calculated_mpg);
        $this->assertSame(1600, $vehicle->fresh()->current_odometer);
        $this->assertSame(0, $other->fuelEntries()->count());
    }

    #[DataProvider('wholeNumberOdometers')]
    public function test_whole_number_decimal_odometers_are_imported_as_integers(string $odometer, int $expected): void
    {
        $vehicle = Vehicle::factory()->create(['current_odometer' => 0]);
        $csv = "model,odometer,gallons,price,fuelup_date\nLegacy,{$odometer},10,3.5,2025-01-01\n";

        $this->actingAs($vehicle->user, 'api')->withCsrf()->postJson("/api/vehicles/{$vehicle->id}/fuel-imports", [
            'file' => UploadedFile::fake()->createWithContent('history.csv', $csv),
        ])->assertOk()->assertJsonPath('data.status', 'success')->assertJsonPath('data.imported', 1);

        $this->assertSame($expected, $vehicle->fuelEntries()->sole()->odometer);
        $this->assertSame($expected, $vehicle->fresh()->current_odometer);
    }

    /** @return array<string, array{string, int}> */
    public static function wholeNumberOdometers(): array
    {
        return [
            'decimal export' => ['10100.0', 10100],
            'zero' => ['0.0', 0],
            'extra zero precision' => ['10100.000', 10100],
        ];
    }

    #[DataProvider('invalidFiles')]
    public function test_invalid_files_report_failures_and_keep_valid_rows(string $csv, ?int $expectedImported = null): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create(['current_odometer' => 100]);

        $response = $this->actingAs($user, 'api')->withCsrf()->postJson("/api/vehicles/{$vehicle->id}/fuel-imports", [
            'file' => UploadedFile::fake()->createWithContent('history.csv', $csv),
        ]);

        if ($expectedImported === null) {
            $response->assertUnprocessable();
        } else {
            $response->assertOk()
                ->assertJsonPath('data.status', $expectedImported > 0 ? 'partial_success' : 'failure')
                ->assertJsonPath('data.imported', $expectedImported)
                ->assertJsonCount(1, 'data.failures');
        }

        $this->assertSame($expectedImported ?? 0, $vehicle->fuelEntries()->count());
        if (! $expectedImported) {
            $this->assertSame(100, $vehicle->fresh()->current_odometer);
        }
    }

    public static function invalidFiles(): array
    {
        $header = "model,odometer,gallons,price,fuelup_date\n";
        $valid = "Legacy,1000,10,3.5,2025-01-01\n";

        return [
            'empty' => [''],
            'headers only' => [$header],
            'missing header' => ["model,odometer,gallons,price\nLegacy,1000,10,3.5\n"],
            'duplicate header' => ["model,odometer,gallons,price,fuelup_date,price\n"],
            'invalid gallons after valid row' => [$header.$valid."Legacy,1300,0,3.5,2025-02-01\n", 1],
            'fractional odometer' => [$header."Legacy,10100.5,10,3.5,2025-01-01\n", 0],
            'decimal odometer over limit' => [$header."Legacy,1000000000.0,10,3.5,2025-01-01\n", 0],
            'negative decimal odometer' => [$header."Legacy,-100.0,10,3.5,2025-01-01\n", 0],
            'negative price' => [$header."Legacy,1000,10,-1,2025-01-01\n", 0],
            'future date' => [$header."Legacy,1000,10,3.5,2099-01-01\n", 0],
            'bad date' => [$header."Legacy,1000,10,3.5,not-a-date\n", 0],
            'invalid precision' => [$header."Legacy,1000,10,3.5555,2025-01-01\n", 0],
            'cost overflow' => [$header."Legacy,1000,9999999,9999999,2025-01-01\n", 0],
            'ragged row' => [$header."Legacy,1000,10\n", 0],
            'conflicting chronology' => [$header.$valid."Legacy,900,10,3.5,2025-02-01\n", 1],
            'duplicate rows' => [$header.$valid.$valid, 1],
            'too many rows' => [$header.str_repeat($valid, 5001)],
        ];
    }

    public function test_reimport_is_rejected_without_changing_existing_history(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();
        $csv = "model,odometer,gallons,price,fuelup_date\nLegacy,1000,10,3.5,2025-01-01\n";
        $this->actingAs($user, 'api')->withCsrf();
        $url = "/api/vehicles/{$vehicle->id}/fuel-imports";
        $this->postJson($url, ['file' => UploadedFile::fake()->createWithContent('history.csv', $csv)])->assertOk();
        $this->postJson($url, ['file' => UploadedFile::fake()->createWithContent('history.csv', $csv)])->assertOk()->assertJsonPath('data.status', 'failure')->assertJsonPath('data.failures.0.row', 2);
        $this->assertSame(1, $vehicle->fuelEntries()->count());
    }

    public function test_import_requires_authentication_ownership_and_an_active_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();
        $url = "/api/vehicles/{$vehicle->id}/fuel-imports";
        $this->postJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'api')->withCsrf()->postJson($url)->assertNotFound();
        $vehicle->forceFill(['archived_at' => now()])->save();
        $this->actingAs($vehicle->user, 'api')->withCsrf()->postJson($url)->assertForbidden();
    }

    public function test_upload_is_required_and_must_be_a_small_csv(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->actingAs($vehicle->user, 'api')->withCsrf();
        $url = "/api/vehicles/{$vehicle->id}/fuel-imports";
        $this->postJson($url)->assertUnprocessable();
        $this->postJson($url, ['file' => UploadedFile::fake()->create('large.csv', 2049, 'text/csv')])->assertUnprocessable();
        $this->postJson($url, ['file' => UploadedFile::fake()->createWithContent('history.txt', 'not csv')])->assertUnprocessable();
    }

    public function test_partial_import_reports_every_failed_row_and_accepts_corrected_rows(): void
    {
        $vehicle = Vehicle::factory()->create(['current_odometer' => 100]);
        $this->actingAs($vehicle->user, 'api')->withCsrf();
        $url = "/api/vehicles/{$vehicle->id}/fuel-imports";
        $header = "model,odometer,gallons,price,fuelup_date\n";
        $csv = $header."Legacy,1000,10,3.5,2025-01-01\nLegacy,1300,0,-1,2025-02-01\nLegacy,1600,10,3.5,2025-03-01\nLegacy,1500,10,3.5,2025-04-01\n";
        $this->postJson($url, ['file' => UploadedFile::fake()->createWithContent('history.csv', $csv)])
            ->assertOk()->assertJsonPath('data.status', 'partial_success')
            ->assertJsonPath('data.imported', 2)->assertJsonPath('data.failed', 2)
            ->assertJsonPath('data.failures.0.row', 3)
            ->assertJsonPath('data.failures.0.values.fuelup_date', '2025-02-01')
            ->assertJsonCount(2, 'data.failures.0.errors')
            ->assertJsonPath('data.failures.1.row', 5);

        $fixed = $header."Legacy,1300,10,3.5,2025-02-01\nLegacy,1900,10,3.5,2025-04-01\n";
        $this->postJson($url, ['file' => UploadedFile::fake()->createWithContent('fixed.csv', $fixed)])
            ->assertOk()->assertJsonPath('data.status', 'success')->assertJsonPath('data.imported', 2);
        $entries = $vehicle->fuelEntries()->chronological()->get();
        $this->assertCount(4, $entries);
        $this->assertSame('30.00', $entries[2]->calculated_mpg);
        $this->assertSame(1900, $vehicle->fresh()->current_odometer);
    }

    public function test_all_invalid_rows_are_reported_as_total_failure(): void
    {
        $vehicle = Vehicle::factory()->create();
        $csv = "model,odometer,gallons,price,fuelup_date\nLegacy,1000,0,3.5,2025-01-01\nLegacy,1300,10,-1,2025-02-01\n";
        $this->actingAs($vehicle->user, 'api')->withCsrf()->postJson("/api/vehicles/{$vehicle->id}/fuel-imports", [
            'file' => UploadedFile::fake()->createWithContent('history.csv', $csv),
        ])->assertOk()->assertJsonPath('data.status', 'failure')->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.failed', 2)->assertJsonPath('data.failures.0.row', 2)->assertJsonPath('data.failures.1.row', 3);
        $this->assertSame(0, $vehicle->fuelEntries()->count());
    }

    private function withCsrf(): static
    {
        $token = Str::random(64);

        return $this->withCredentials()
            ->withUnencryptedCookie(config('jwt.csrf.cookie_name'), $token)
            ->withHeader(config('jwt.csrf.header_name'), $token);
    }
}

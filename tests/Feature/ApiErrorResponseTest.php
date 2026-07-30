<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    public function test_unknown_api_routes_use_the_application_error_shape(): void
    {
        $response = $this->getJson('/api/missing');

        $response
            ->assertNotFound()
            ->assertExactJson([
                'errors' => [
                    [
                        'status' => '404',
                        'code' => 'not_found',
                        'detail' => 'The requested resource was not found.',
                    ],
                ],
            ]);
    }

    public function test_api_validation_errors_include_stable_codes_and_source_pointers(): void
    {
        Route::post('/api/test-validation', function (Request $request): void {
            $request->validate([
                'odometer' => ['required', 'integer'],
            ]);
        });

        $response = $this->postJson('/api/test-validation');

        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'errors' => [
                    [
                        'status' => '422',
                        'code' => 'validation_failed',
                        'detail' => 'The odometer field is required.',
                        'source' => [
                            'pointer' => '/data/attributes/odometer',
                        ],
                    ],
                ],
            ]);
    }
}

<?php

use App\Http\Controllers\Api\Auth\CurrentUserController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RefreshController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\FuelEntryController;
use App\Http\Controllers\Api\MaintenanceEventController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VehicleDashboardController;
use App\Http\Controllers\Api\VehicleTimelineController;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::get('/csrf-cookie', function () {
        return response()->noContent()->withCookie(Controller::makeCsrfCookie());
    })->name('api.auth.csrf-cookie');

    Route::middleware('csrf.double-submit')->group(function (): void {
        Route::post('/register', RegisterController::class)
            ->middleware('throttle:auth-register')
            ->name('api.auth.register');
        Route::post('/login', LoginController::class)
            ->middleware('throttle:auth-login')
            ->name('api.auth.login');
        Route::post('/refresh', RefreshController::class)
            ->middleware('throttle:auth-refresh')
            ->name('api.auth.refresh');
        Route::post('/logout', LogoutController::class)
            ->middleware(['auth:api', 'throttle:auth-refresh'])
            ->name('api.auth.logout');
        Route::post('/forgot-password', ForgotPasswordController::class)
            ->middleware('throttle:auth-password')
            ->name('api.auth.forgot-password');
        Route::post('/reset-password', ResetPasswordController::class)
            ->middleware('throttle:auth-password')
            ->name('api.auth.reset-password');
    });

    Route::get('/user', CurrentUserController::class)
        ->middleware('auth:api')
        ->name('api.auth.user');
});

Route::middleware(['auth:api', 'csrf.double-submit'])->group(function (): void {
    Route::apiResource('vehicles', VehicleController::class)->except('destroy');
    Route::post('/vehicles/{vehicle}/archive', [VehicleController::class, 'archive'])->name('api.vehicles.archive');
    Route::post('/vehicles/{vehicle}/restore', [VehicleController::class, 'restore'])->name('api.vehicles.restore');
    Route::get('/vehicles/{vehicle}/dashboard', VehicleDashboardController::class)->name('api.vehicles.dashboard');
    Route::get('/vehicles/{vehicle}/timeline', VehicleTimelineController::class)->name('api.vehicles.timeline');
    Route::get('/vehicles/{vehicle}/fuel-entries', [FuelEntryController::class, 'index'])->name('api.vehicles.fuel-entries.index');
    Route::post('/vehicles/{vehicle}/fuel-entries', [FuelEntryController::class, 'store'])->name('api.vehicles.fuel-entries.store');
    Route::get('/fuel-entries/{fuelEntry}', [FuelEntryController::class, 'show'])->name('api.fuel-entries.show');
    Route::patch('/fuel-entries/{fuelEntry}', [FuelEntryController::class, 'update'])->name('api.fuel-entries.update');
    Route::delete('/fuel-entries/{fuelEntry}', [FuelEntryController::class, 'destroy'])->name('api.fuel-entries.destroy');
    Route::get('/vehicles/{vehicle}/maintenance-events', [MaintenanceEventController::class, 'index'])->name('api.vehicles.maintenance-events.index');
    Route::post('/vehicles/{vehicle}/maintenance-events', [MaintenanceEventController::class, 'store'])->name('api.vehicles.maintenance-events.store');
    Route::get('/maintenance-events/{maintenanceEvent}', [MaintenanceEventController::class, 'show'])->name('api.maintenance-events.show');
    Route::patch('/maintenance-events/{maintenanceEvent}', [MaintenanceEventController::class, 'update'])->name('api.maintenance-events.update');
    Route::delete('/maintenance-events/{maintenanceEvent}', [MaintenanceEventController::class, 'destroy'])->name('api.maintenance-events.destroy');
});

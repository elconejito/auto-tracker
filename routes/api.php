<?php

use App\Http\Controllers\Api\Auth\CurrentUserController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RefreshController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
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

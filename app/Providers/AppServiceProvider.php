<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip().'|'.strtolower((string) $request->input('email'))));
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute(3)
            ->by($request->ip()));
        RateLimiter::for('auth-refresh', fn (Request $request) => Limit::perMinute(10)
            ->by($request->ip()));
        RateLimiter::for('auth-password', fn (Request $request) => Limit::perMinute(3)
            ->by($request->ip().'|'.strtolower((string) $request->input('email'))));

        ResetPassword::createUrlUsing(
            fn (object $notifiable, string $token): string => rtrim((string) config('app.url'), '/')
                .'/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset()),
        );
    }
}

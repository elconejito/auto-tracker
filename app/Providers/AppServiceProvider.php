<?php

namespace App\Providers;

use App\Models\Services\AirFilterReplacement;
use App\Models\Services\BatteryReplacement;
use App\Models\Services\BrakePadReplacement;
use App\Models\Services\BrakeRotorReplacement;
use App\Models\Services\CabinAirFilterReplacement;
use App\Models\Services\CoolantService;
use App\Models\Services\GenericService;
use App\Models\Services\OilChange;
use App\Models\Services\SerpentineBeltReplacement;
use App\Models\Services\SparkPlugReplacement;
use App\Models\Services\TimingBeltReplacement;
use App\Models\Services\TireReplacement;
use App\Models\Services\TireRotation;
use App\Models\Services\TransmissionService;
use App\Models\Services\VehicleInspection;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        Relation::enforceMorphMap([
            'oil_change' => OilChange::class,
            'tire_replacement' => TireReplacement::class,
            'tire_rotation' => TireRotation::class,
            'brake_pad_replacement' => BrakePadReplacement::class,
            'brake_rotor_replacement' => BrakeRotorReplacement::class,
            'timing_belt_replacement' => TimingBeltReplacement::class,
            'serpentine_belt_replacement' => SerpentineBeltReplacement::class,
            'battery_replacement' => BatteryReplacement::class,
            'air_filter_replacement' => AirFilterReplacement::class,
            'cabin_air_filter_replacement' => CabinAirFilterReplacement::class,
            'spark_plug_replacement' => SparkPlugReplacement::class,
            'transmission_service' => TransmissionService::class,
            'coolant_service' => CoolantService::class,
            'vehicle_inspection' => VehicleInspection::class,
            'generic_service' => GenericService::class,
        ]);

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

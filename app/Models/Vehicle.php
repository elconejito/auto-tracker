<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nickname',
        'year',
        'make',
        'model',
        'trim',
        'vin',
        'license_plate',
        'current_odometer',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'current_odometer' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<FuelEntry, $this>
     */
    public function fuelEntries(): HasMany
    {
        return $this->hasMany(FuelEntry::class);
    }

    /**
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    public function scopeWithFuelSummaries(Builder $query): Builder
    {
        return $query
            ->addSelect([
                'latest_mpg' => FuelEntry::query()
                    ->select('calculated_mpg')
                    ->whereColumn('vehicle_id', 'vehicles.id')
                    ->whereNotNull('calculated_mpg')
                    ->orderByDesc('fueled_at')
                    ->orderByDesc('id')
                    ->limit(1),
                'most_recent_fueled_at' => FuelEntry::query()
                    ->select('fueled_at')
                    ->whereColumn('vehicle_id', 'vehicles.id')
                    ->latest('fueled_at')
                    ->latest('id')
                    ->limit(1),
            ])
            ->addSelect([
                'average_mpg' => FuelEntry::query()
                    ->selectRaw('AVG(calculated_mpg)')
                    ->whereColumn('vehicle_id', 'vehicles.id')
                    ->whereNotNull('calculated_mpg'),
            ])
            ->withCasts([
                'latest_mpg' => 'decimal:2',
                'average_mpg' => 'decimal:2',
                'most_recent_fueled_at' => 'datetime',
            ]);
    }

    /**
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $user = auth('api')->user();

        if (! $user instanceof User) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        return $query
            ->whereBelongsTo($user)
            ->where($field ?? $this->getRouteKeyName(), $value);
    }
}

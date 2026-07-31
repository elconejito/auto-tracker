<?php

namespace App\Models;

use Database\Factories\FuelEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelEntry extends Model
{
    /** @use HasFactory<FuelEntryFactory> */
    use HasFactory, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'vehicle_id',
        'fueled_at',
        'odometer',
        'gallons',
        'total_cost',
        'price_per_gallon',
        'miles_driven',
        'calculated_mpg',
        'station',
        'fuel_grade',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fueled_at' => 'datetime',
            'odometer' => 'integer',
            'gallons' => 'decimal:3',
            'total_cost' => 'decimal:2',
            'price_per_gallon' => 'decimal:3',
            'miles_driven' => 'integer',
            'calculated_mpg' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @param  Builder<FuelEntry>  $query
     * @return Builder<FuelEntry>
     */
    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('fueled_at')->orderBy('id');
    }

    /**
     * @param  Builder<FuelEntry>  $query
     * @return Builder<FuelEntry>
     */
    public function scopeForVehicle(Builder $query, Vehicle $vehicle): Builder
    {
        return $query->whereBelongsTo($vehicle);
    }

    /**
     * @param  Builder<FuelEntry>  $query
     * @return Builder<FuelEntry>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $user = auth('api')->user();

        if (! $user instanceof User) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        return $query
            ->whereIn('vehicle_id', Vehicle::query()->whereBelongsTo($user)->select('id'))
            ->where($field ?? $this->getRouteKeyName(), $value);
    }
}

<?php

namespace App\Models;

use Database\Factories\MaintenanceEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceEvent extends Model
{
    /** @use HasFactory<MaintenanceEventFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'vehicle_id',
        'performed_at',
        'odometer',
        'provider',
        'total_cost',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'odometer' => 'integer',
            'total_cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return HasMany<ServiceLine, $this> */
    public function serviceLines(): HasMany
    {
        return $this->hasMany(ServiceLine::class);
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
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

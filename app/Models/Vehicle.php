<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

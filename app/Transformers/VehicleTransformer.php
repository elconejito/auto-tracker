<?php

namespace App\Transformers;

use App\Models\Vehicle;
use League\Fractal\TransformerAbstract;

final class VehicleTransformer extends TransformerAbstract
{
    /**
     * @return array{id: string, type: string, attributes: array{nickname: string, year: int, make: string, model: string, trim: string|null, vin: string|null, license_plate: string|null, current_odometer: int, archived_at: string|null, created_at: string|null, updated_at: string|null}}
     */
    public function transform(Vehicle $vehicle): array
    {
        return [
            'id' => (string) $vehicle->getKey(),
            'type' => 'vehicles',
            'attributes' => [
                'nickname' => $vehicle->nickname,
                'year' => $vehicle->year,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'trim' => $vehicle->trim,
                'vin' => $vehicle->vin,
                'license_plate' => $vehicle->license_plate,
                'current_odometer' => $vehicle->current_odometer,
                'archived_at' => $vehicle->archived_at?->toAtomString(),
                'created_at' => $vehicle->created_at?->toAtomString(),
                'updated_at' => $vehicle->updated_at?->toAtomString(),
            ],
        ];
    }
}

<?php

namespace App\Transformers;

use App\Models\Vehicle;
use League\Fractal\TransformerAbstract;

final class VehicleTransformer extends TransformerAbstract
{
    /**
     * @return array{id: string, type: string, attributes: array<string, mixed>}
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
                'latest_mpg' => $vehicle->getAttribute('latest_mpg'),
                'average_mpg' => $vehicle->getAttribute('average_mpg'),
                'most_recent_fueled_at' => $vehicle->getAttribute('most_recent_fueled_at')?->toAtomString(),
                'archived_at' => $vehicle->archived_at?->toAtomString(),
                'created_at' => $vehicle->created_at?->toAtomString(),
                'updated_at' => $vehicle->updated_at?->toAtomString(),
            ],
        ];
    }
}

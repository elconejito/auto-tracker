<?php

namespace App\Transformers;

use App\Models\FuelEntry;
use League\Fractal\TransformerAbstract;

final class FuelEntryTransformer extends TransformerAbstract
{
    /**
     * @return array{id: string, type: string, attributes: array<string, mixed>}
     */
    public function transform(FuelEntry $fuelEntry): array
    {
        return [
            'id' => (string) $fuelEntry->getKey(),
            'type' => 'fuel_entries',
            'relationships' => [
                'vehicle' => [
                    'data' => [
                        'type' => 'vehicles',
                        'id' => (string) $fuelEntry->vehicle_id,
                    ],
                ],
            ],
            'attributes' => [
                'fueled_at' => $fuelEntry->fueled_at?->toAtomString(),
                'odometer' => $fuelEntry->odometer,
                'gallons' => $fuelEntry->gallons,
                'total_cost' => $fuelEntry->total_cost,
                'price_per_gallon' => $fuelEntry->price_per_gallon,
                'miles_driven' => $fuelEntry->miles_driven,
                'calculated_mpg' => $fuelEntry->calculated_mpg,
                'station' => $fuelEntry->station,
                'fuel_grade' => $fuelEntry->fuel_grade,
                'notes' => $fuelEntry->notes,
                'created_at' => $fuelEntry->created_at?->toAtomString(),
                'updated_at' => $fuelEntry->updated_at?->toAtomString(),
            ],
        ];
    }
}

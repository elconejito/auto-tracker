<?php

namespace App\Transformers;

use App\Models\MaintenanceEvent;
use League\Fractal\TransformerAbstract;

final class MaintenanceEventTransformer extends TransformerAbstract
{
    public function transform(MaintenanceEvent $event): array
    {
        return [
            'id' => (string) $event->getKey(),
            'type' => 'maintenance_events',
            'relationships' => [
                'vehicle' => ['data' => ['type' => 'vehicles', 'id' => (string) $event->vehicle_id]],
            ],
            'attributes' => [
                'performed_at' => $event->performed_at?->toAtomString(),
                'odometer' => $event->odometer,
                'provider' => $event->provider,
                'total_cost' => $event->total_cost,
                'notes' => $event->notes,
                'services' => $event->serviceLines->map(fn ($line) => (new ServiceLineTransformer)->transform($line))->all(),
                'created_at' => $event->created_at?->toAtomString(),
                'updated_at' => $event->updated_at?->toAtomString(),
            ],
        ];
    }
}

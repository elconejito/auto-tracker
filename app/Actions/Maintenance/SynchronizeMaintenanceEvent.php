<?php

namespace App\Actions\Maintenance;

use App\Models\MaintenanceEvent;
use Illuminate\Support\Arr;

final class SynchronizeMaintenanceEvent
{
    /** @param list<array<string, mixed>> $services */
    public function handle(MaintenanceEvent $event, array $services): void
    {
        $event->serviceLines()->each(function ($line): void {
            $line->serviceable()->delete();
            $line->delete();
        });

        foreach ($services as $serviceData) {
            $modelClass = ServiceCatalog::modelFor($serviceData['type']);
            $service = new $modelClass;
            $service->forceFill(Arr::only($serviceData['details'] ?? [], $service->getFillable()));
            $service->save();
            $event->serviceLines()->create([
                'line_cost' => $serviceData['line_cost'] ?? null,
                'serviceable_type' => $serviceData['type'],
                'serviceable_id' => $service->getKey(),
            ]);
        }
    }
}

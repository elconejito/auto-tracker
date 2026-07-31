<?php

namespace App\Actions\Maintenance;

use App\Models\MaintenanceEvent;
use App\Models\Vehicle;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateMaintenanceEvent
{
    public function __construct(private SynchronizeMaintenanceEvent $synchronize, private ValidateMaintenanceChronology $validateChronology) {}

    /** @param array<string, mixed> $values */
    public function handle(MaintenanceEvent $event, array $values): MaintenanceEvent
    {
        return DB::transaction(function () use ($event, $values): MaintenanceEvent {
            $vehicle = Vehicle::query()->whereKey($event->vehicle_id)->lockForUpdate()->firstOrFail();
            $event = MaintenanceEvent::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();
            $event->fill(Arr::except($values, 'services'))->save();
            if (array_key_exists('services', $values)) {
                $this->synchronize->handle($event, $values['services']);
            }
            $this->validateChronology->handle($vehicle);
            $vehicle->forceFill(['current_odometer' => max($vehicle->current_odometer, $event->odometer)])->save();

            return $event->load('serviceLines.serviceable');
        });
    }
}

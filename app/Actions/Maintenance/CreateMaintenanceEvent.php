<?php

namespace App\Actions\Maintenance;

use App\Models\MaintenanceEvent;
use App\Models\Vehicle;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateMaintenanceEvent
{
    public function __construct(private SynchronizeMaintenanceEvent $synchronize, private ValidateMaintenanceChronology $validateChronology) {}

    /** @param array<string, mixed> $values */
    public function handle(Vehicle $vehicle, array $values): MaintenanceEvent
    {
        return DB::transaction(function () use ($vehicle, $values): MaintenanceEvent {
            $vehicle = Vehicle::query()->whereKey($vehicle->getKey())->lockForUpdate()->firstOrFail();
            $event = new MaintenanceEvent(Arr::except($values, 'services'));
            $event->forceFill(['id' => (string) Str::ulid(), 'vehicle_id' => $vehicle->getKey()])->save();
            $this->synchronize->handle($event, $values['services']);
            $this->validateChronology->handle($vehicle);
            $vehicle->forceFill(['current_odometer' => max($vehicle->current_odometer, $event->odometer)])->save();

            return $event->load('serviceLines.serviceable');
        });
    }
}

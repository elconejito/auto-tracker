<?php

namespace App\Actions\Maintenance;

use App\Models\MaintenanceEvent;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

final class DeleteMaintenanceEvent
{
    public function handle(MaintenanceEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            Vehicle::query()->whereKey($event->vehicle_id)->lockForUpdate()->firstOrFail();
            $event = MaintenanceEvent::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();
            $event->load('serviceLines.serviceable');
            $event->serviceLines->each(fn ($line) => $line->serviceable()->delete());
            $event->delete();
        });
    }
}

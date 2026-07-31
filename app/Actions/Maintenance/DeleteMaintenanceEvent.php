<?php

namespace App\Actions\Maintenance;

use App\Models\MaintenanceEvent;
use Illuminate\Support\Facades\DB;

final class DeleteMaintenanceEvent
{
    public function handle(MaintenanceEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            $event->load('serviceLines.serviceable');
            $event->serviceLines->each(fn ($line) => $line->serviceable()->delete());
            $event->delete();
        });
    }
}

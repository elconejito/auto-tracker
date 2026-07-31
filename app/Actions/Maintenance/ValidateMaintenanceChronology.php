<?php

namespace App\Actions\Maintenance;

use App\Exceptions\InvalidMaintenanceChronologyException;
use App\Models\FuelEntry;
use App\Models\MaintenanceEvent;
use App\Models\Vehicle;

final class ValidateMaintenanceChronology
{
    public function handle(Vehicle $vehicle): void
    {
        $readings = FuelEntry::query()->whereBelongsTo($vehicle)->get(['id', 'fueled_at', 'odometer'])
            ->map(fn (FuelEntry $entry): array => ['id' => $entry->id, 'at' => $entry->fueled_at, 'odometer' => $entry->odometer])
            ->concat(MaintenanceEvent::query()->whereBelongsTo($vehicle)->get(['id', 'performed_at', 'odometer'])
                ->map(fn (MaintenanceEvent $event): array => ['id' => $event->id, 'at' => $event->performed_at, 'odometer' => $event->odometer]))
            ->sortBy([['at', 'asc'], ['id', 'asc']])
            ->values();

        $readings->each(function (array $reading, int $index) use ($readings): void {
            if ($index > 0 && $reading['odometer'] < $readings[$index - 1]['odometer']) {
                throw new InvalidMaintenanceChronologyException('Odometer readings must not decrease over time.');
            }
        });
    }
}

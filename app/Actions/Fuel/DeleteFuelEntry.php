<?php

namespace App\Actions\Fuel;

use App\Models\FuelEntry;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

final class DeleteFuelEntry
{
    public function __construct(
        private RecalculateVehicleFuelEconomy $recalculateVehicleFuelEconomy,
        private SynchronizeVehicleOdometer $synchronizeVehicleOdometer,
    ) {}

    public function handle(FuelEntry $fuelEntry): void
    {
        DB::transaction(function () use ($fuelEntry): void {
            $vehicle = Vehicle::query()->whereKey($fuelEntry->vehicle_id)->lockForUpdate()->firstOrFail();
            $lockedEntries = FuelEntry::query()->forVehicle($vehicle)->chronological()->lockForUpdate()->get();
            $lockedEntries->firstWhere('id', $fuelEntry->getKey())?->delete();

            $remainingEntries = $lockedEntries->reject(
                fn (FuelEntry $entry): bool => $entry->getKey() === $fuelEntry->getKey(),
            );
            $this->recalculateVehicleFuelEconomy->recalculate($remainingEntries);
            $this->synchronizeVehicleOdometer->handle($vehicle, $remainingEntries);
        });
    }
}

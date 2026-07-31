<?php

namespace App\Actions\Fuel;

use App\Exceptions\InvalidFuelChronologyException;
use App\Models\FuelEntry;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RecalculateVehicleFuelEconomy
{
    public function __construct(private SynchronizeVehicleOdometer $synchronizeVehicleOdometer) {}

    public function handle(Vehicle $vehicle): Vehicle
    {
        return DB::transaction(function () use ($vehicle): Vehicle {
            $lockedVehicle = Vehicle::query()->whereKey($vehicle->getKey())->lockForUpdate()->firstOrFail();
            $entries = FuelEntry::query()->forVehicle($lockedVehicle)->chronological()->lockForUpdate()->get();

            $this->recalculate($entries);
            $this->synchronizeVehicleOdometer->handle($lockedVehicle, $entries);

            return $lockedVehicle->refresh();
        });
    }

    /**
     * @param  Collection<int, FuelEntry>  $entries
     */
    public function recalculate(Collection $entries): void
    {
        $previousOdometer = null;

        foreach ($entries as $entry) {
            $odometer = (int) $entry->odometer;

            if ($previousOdometer !== null && $odometer <= $previousOdometer) {
                throw new InvalidFuelChronologyException;
            }

            $milesDriven = $previousOdometer === null ? null : $odometer - $previousOdometer;
            $gallonsMilli = FuelDecimal::toScaledInt($entry->gallons, 3);

            $entry->forceFill([
                'miles_driven' => $milesDriven,
                'calculated_mpg' => $milesDriven === null || $gallonsMilli === null
                    ? null
                    : FuelDecimal::formatScaledInt(FuelDecimal::mpgHundredths($milesDriven, $gallonsMilli), 2),
            ])->save();

            $previousOdometer = $odometer;
        }
    }

    /**
     * @param  Collection<int, FuelEntry>  $entries
     */
    public function synchronizeOdometer(Vehicle $vehicle, Collection $entries): void
    {
        $this->synchronizeVehicleOdometer->handle($vehicle, $entries);
    }
}

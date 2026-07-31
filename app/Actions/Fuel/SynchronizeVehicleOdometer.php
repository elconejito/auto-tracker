<?php

namespace App\Actions\Fuel;

use App\Models\FuelEntry;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

final class SynchronizeVehicleOdometer
{
    /**
     * @param  Collection<int, FuelEntry>|null  $entries
     */
    public function handle(Vehicle $vehicle, ?Collection $entries = null): Vehicle
    {
        $latestOdometer = $entries?->last()?->odometer
            ?? FuelEntry::query()->forVehicle($vehicle)->chronological()->get()->last()?->odometer;

        if ($latestOdometer !== null && (int) $latestOdometer > (int) $vehicle->current_odometer) {
            $vehicle->forceFill(['current_odometer' => (int) $latestOdometer])->save();
        }

        return $vehicle;
    }
}

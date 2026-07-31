<?php

namespace App\Actions\Fuel;

use App\Models\FuelEntry;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class UpdateFuelEntry
{
    public function __construct(private RecalculateVehicleFuelEconomy $recalculateVehicleFuelEconomy) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(FuelEntry $fuelEntry, array $values): FuelEntry
    {
        return DB::transaction(function () use ($fuelEntry, $values): FuelEntry {
            $vehicle = Vehicle::query()->whereKey($fuelEntry->vehicle_id)->lockForUpdate()->firstOrFail();
            $lockedEntries = FuelEntry::query()->forVehicle($vehicle)->chronological()->lockForUpdate()->get();
            $lockedFuelEntry = $lockedEntries->firstWhere('id', $fuelEntry->getKey());

            if (! $lockedFuelEntry instanceof FuelEntry) {
                throw (new ModelNotFoundException)->setModel(FuelEntry::class, [$fuelEntry->getKey()]);
            }

            $mergedValues = array_merge($lockedFuelEntry->only([
                'fueled_at',
                'odometer',
                'gallons',
                'total_cost',
                'price_per_gallon',
                'station',
                'fuel_grade',
                'notes',
            ]), $values);

            if (array_key_exists('total_cost', $values) && ! array_key_exists('price_per_gallon', $values)) {
                $mergedValues['price_per_gallon'] = null;
            }

            if (array_key_exists('price_per_gallon', $values) && ! array_key_exists('total_cost', $values)) {
                $mergedValues['total_cost'] = null;
            }

            $lockedFuelEntry->fill([
                ...$mergedValues,
                ...FuelEntryValues::normalize($mergedValues),
            ])->save();

            $chronologicalEntries = FuelEntry::query()->forVehicle($vehicle)->chronological()->get();

            $this->recalculateVehicleFuelEconomy->recalculate($chronologicalEntries);
            $this->recalculateVehicleFuelEconomy->synchronizeOdometer($vehicle, $chronologicalEntries);

            return $lockedFuelEntry->refresh();
        });
    }
}

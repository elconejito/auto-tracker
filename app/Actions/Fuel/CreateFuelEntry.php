<?php

namespace App\Actions\Fuel;

use App\Models\FuelEntry;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateFuelEntry
{
    public function __construct(private RecalculateVehicleFuelEconomy $recalculateVehicleFuelEconomy) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(Vehicle $vehicle, array $values): FuelEntry
    {
        return DB::transaction(function () use ($vehicle, $values): FuelEntry {
            $lockedVehicle = Vehicle::query()->whereKey($vehicle->getKey())->lockForUpdate()->firstOrFail();
            $normalized = FuelEntryValues::normalize($values);
            $fuelEntry = new FuelEntry([
                ...$values,
                ...$normalized,
                'vehicle_id' => $lockedVehicle->getKey(),
            ]);
            $fuelEntry->forceFill(['id' => (string) Str::ulid()])->save();

            $this->recalculateVehicleFuelEconomy->handle($lockedVehicle);

            return $fuelEntry->refresh();
        });
    }
}

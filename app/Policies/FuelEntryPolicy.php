<?php

namespace App\Policies;

use App\Models\FuelEntry;
use App\Models\User;
use App\Models\Vehicle;

class FuelEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FuelEntry $fuelEntry): bool
    {
        return $this->owns($user, $fuelEntry);
    }

    public function create(User $user, Vehicle $vehicle): bool
    {
        return $user->getKey() === $vehicle->user_id && $vehicle->archived_at === null;
    }

    public function update(User $user, FuelEntry $fuelEntry): bool
    {
        return $this->owns($user, $fuelEntry) && $fuelEntry->vehicle->archived_at === null;
    }

    public function delete(User $user, FuelEntry $fuelEntry): bool
    {
        return $this->owns($user, $fuelEntry) && $fuelEntry->vehicle->archived_at === null;
    }

    private function owns(User $user, FuelEntry $fuelEntry): bool
    {
        return $fuelEntry->vehicle->user_id === $user->getKey();
    }
}

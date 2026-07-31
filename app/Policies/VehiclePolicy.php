<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->getKey() === $vehicle->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->getKey() === $vehicle->user_id && $vehicle->archived_at === null;
    }

    /**
     * Determine whether the user can archive the model.
     */
    public function archive(User $user, Vehicle $vehicle): bool
    {
        return $user->getKey() === $vehicle->user_id && $vehicle->archived_at === null;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Vehicle $vehicle): bool
    {
        return $user->getKey() === $vehicle->user_id && $vehicle->archived_at !== null;
    }
}

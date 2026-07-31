<?php

namespace App\Policies;

use App\Models\MaintenanceEvent;
use App\Models\User;
use App\Models\Vehicle;

final class MaintenanceEventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MaintenanceEvent $event): bool
    {
        return $event->vehicle->user_id === $user->getKey();
    }

    public function create(User $user, Vehicle $vehicle): bool
    {
        return $vehicle->user_id === $user->getKey() && $vehicle->archived_at === null;
    }

    public function update(User $user, MaintenanceEvent $event): bool
    {
        return $this->view($user, $event) && $event->vehicle->archived_at === null;
    }

    public function delete(User $user, MaintenanceEvent $event): bool
    {
        return $this->update($user, $event);
    }
}

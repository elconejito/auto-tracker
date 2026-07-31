<?php

namespace App\Http\Requests;

use App\Models\MaintenanceEvent;
use App\Models\Vehicle;

final class StoreMaintenanceEventRequest extends MaintenanceEventRequest
{
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');

        return $vehicle instanceof Vehicle && ($this->user()?->can('create', [MaintenanceEvent::class, $vehicle]) ?? false);
    }

    public function rules(): array
    {
        return $this->maintenanceRules();
    }
}

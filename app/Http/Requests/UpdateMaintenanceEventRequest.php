<?php

namespace App\Http\Requests;

use App\Models\MaintenanceEvent;

final class UpdateMaintenanceEventRequest extends MaintenanceEventRequest
{
    public function authorize(): bool
    {
        $event = $this->route('maintenanceEvent');

        return $event instanceof MaintenanceEvent && ($this->user()?->can('update', $event) ?? false);
    }

    public function rules(): array
    {
        return $this->maintenanceRules(partial: true);
    }
}

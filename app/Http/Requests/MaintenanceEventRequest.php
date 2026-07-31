<?php

namespace App\Http\Requests;

use App\Actions\Maintenance\ServiceCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class MaintenanceEventRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function maintenanceRules(bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'performed_at' => [$presence, 'date', 'before_or_equal:now'],
            'odometer' => [$presence, 'integer', 'min:0'],
            'provider' => [$partial ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:150'],
            'total_cost' => [$partial ? 'sometimes' : 'nullable', 'nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'notes' => [$partial ? 'sometimes' : 'nullable', 'nullable', 'string'],
            'services' => [$presence, 'array', 'min:1'],
            'services.*.type' => ['required', 'string', Rule::in(ServiceCatalog::aliases())],
            'services.*.line_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'services.*.details' => ['present', 'array'],
            'services.*.details.name' => ['nullable', 'string', 'max:150'],
            'services.*.details.description' => ['nullable', 'string'],
            'services.*.details.notes' => ['nullable', 'string'],
            'services.*.details.position' => ['nullable', Rule::in(['all', 'front', 'rear', 'front_left', 'front_right', 'rear_left', 'rear_right'])],
            'services.*.details.manufacturer' => ['nullable', 'string', 'max:150'],
            'services.*.details.model' => ['nullable', 'string', 'max:150'],
            'services.*.details.part_number' => ['nullable', 'string', 'max:100'],
            'services.*.details.filter_part_number' => ['nullable', 'string', 'max:100'],
            'services.*.details.viscosity' => ['nullable', 'string', 'max:50'],
            'services.*.details.quantity' => ['nullable', 'numeric', 'gt:0'],
            'services.*.details.size' => ['nullable', 'string', 'max:100'],
            'services.*.details.pattern' => ['nullable', 'string', 'max:150'],
            'services.*.details.filter_replaced' => ['nullable', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('services', []) as $index => $service) {
                if (($service['type'] ?? null) === 'generic_service' && blank($service['details']['name'] ?? null)) {
                    $validator->errors()->add("services.{$index}.details.name", 'A name is required for generic service work.');
                }
            }
        }];
    }
}

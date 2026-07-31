<?php

namespace App\Http\Requests;

use App\Models\MaintenanceEvent;
use Illuminate\Foundation\Http\FormRequest;

final class IndexMaintenanceEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', MaintenanceEvent::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.performed_from' => ['nullable', 'date'],
            'filter.performed_to' => ['nullable', 'date'],
            'filter.service_type' => ['nullable', 'string'],
            'sort' => ['nullable', 'string'],
            'page' => ['nullable', 'array'],
            'page.number' => ['nullable', 'integer', 'min:1'],
            'page.size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

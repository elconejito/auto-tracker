<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

final class IndexVehicleTimelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');

        return $vehicle instanceof Vehicle && ($this->user()?->can('view', $vehicle) ?? false);
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'array'],
            'page.number' => ['nullable', 'integer', 'min:1'],
            'page.size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

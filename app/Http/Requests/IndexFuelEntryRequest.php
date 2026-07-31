<?php

namespace App\Http\Requests;

use App\Models\FuelEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexFuelEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', FuelEntry::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.fueled_from' => ['nullable', 'date'],
            'filter.fueled_to' => ['nullable', 'date'],
            'filter.odometer_min' => ['nullable', 'integer', 'min:0'],
            'filter.odometer_max' => ['nullable', 'integer', 'min:0'],
            'filter.mpg_min' => ['nullable', 'numeric', 'min:0'],
            'filter.mpg_max' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', 'string'],
            'page' => ['nullable', 'array'],
            'page.number' => ['nullable', 'integer', 'min:1'],
            'page.size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

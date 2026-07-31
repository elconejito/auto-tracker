<?php

namespace App\Http\Requests;

use App\Models\FuelEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFuelEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fuelEntry = $this->route('fuelEntry');

        return $fuelEntry instanceof FuelEntry
            && ($this->user()?->can('update', $fuelEntry) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fueled_at' => ['sometimes', 'required', 'date', 'before_or_equal:now'],
            'odometer' => ['sometimes', 'required', 'integer', 'min:0'],
            'gallons' => ['sometimes', 'required', 'numeric', 'gt:0', 'decimal:0,3'],
            'total_cost' => ['sometimes', 'nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'price_per_gallon' => ['sometimes', 'nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'station' => ['sometimes', 'nullable', 'string', 'max:150'],
            'fuel_grade' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}

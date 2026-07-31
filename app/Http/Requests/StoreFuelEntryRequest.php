<?php

namespace App\Http\Requests;

use App\Models\FuelEntry;
use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFuelEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');

        return $vehicle instanceof Vehicle
            && ($this->user()?->can('create', [FuelEntry::class, $vehicle]) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fueled_at' => ['required', 'date', 'before_or_equal:now'],
            'odometer' => ['required', 'integer', 'min:0'],
            'gallons' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'total_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'price_per_gallon' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'station' => ['nullable', 'string', 'max:150'],
            'fuel_grade' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

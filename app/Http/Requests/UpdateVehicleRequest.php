<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateVehicleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');

        return $vehicle instanceof Vehicle
            && ($this->user()?->can('update', $vehicle) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nickname' => ['sometimes', 'required', 'string', 'max:100'],
            'year' => ['sometimes', 'required', 'integer', 'between:1886,'.(now()->year + 1)],
            'make' => ['sometimes', 'required', 'string', 'max:100'],
            'model' => ['sometimes', 'required', 'string', 'max:100'],
            'trim' => ['sometimes', 'nullable', 'string', 'max:100'],
            'vin' => ['sometimes', 'nullable', 'string', 'max:32'],
            'license_plate' => ['sometimes', 'nullable', 'string', 'max:32'],
            'current_odometer' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $vehicle = $this->route('vehicle');

                if (! $this->has('current_odometer') || ! $vehicle instanceof Vehicle) {
                    return;
                }

                $latestFuelOdometer = $vehicle->fuelEntries()->max('odometer');

                if ($latestFuelOdometer !== null && $this->integer('current_odometer') < (int) $latestFuelOdometer) {
                    $validator->errors()->add(
                        'current_odometer',
                        'The current odometer cannot be lower than the latest fuel entry.',
                    );
                }
            },
        ];
    }
}

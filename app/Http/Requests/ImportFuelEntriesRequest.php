<?php

namespace App\Http\Requests;

class ImportFuelEntriesRequest extends StoreFuelEntryRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Actions\Fuel\ImportFuelEntries;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportFuelEntriesRequest;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class FuelImportController extends Controller
{
    public function __invoke(ImportFuelEntriesRequest $request, Vehicle $vehicle, ImportFuelEntries $import): JsonResponse
    {
        return response()->json(['data' => $import->handle($vehicle, $request->file('file'))]);
    }
}

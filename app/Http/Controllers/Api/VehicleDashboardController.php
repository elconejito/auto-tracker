<?php

namespace App\Http\Controllers\Api;

use App\Actions\Dashboard\BuildVehicleDashboard;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Transformers\VehicleDashboardTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class VehicleDashboardController extends Controller
{
    public function __invoke(Vehicle $vehicle, BuildVehicleDashboard $build): JsonResponse
    {
        Gate::authorize('view', $vehicle);

        return $this->respondWithItem($build->handle($vehicle), new VehicleDashboardTransformer);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexVehicleTimelineRequest;
use App\Models\Vehicle;
use App\Queries\VehicleTimeline;
use App\Transformers\TimelineItemTransformer;
use Illuminate\Http\JsonResponse;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;

final class VehicleTimelineController extends Controller
{
    public function __invoke(IndexVehicleTimelineRequest $request, Vehicle $vehicle, VehicleTimeline $timeline): JsonResponse
    {
        $paginator = $timeline->paginate($vehicle, $request->integer('page.size', 15), $request->integer('page.number', 1));

        return fractal()->collection($paginator->getCollection(), new TimelineItemTransformer)
            ->paginateWith(new IlluminatePaginatorAdapter($paginator))->respond();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexVehicleRequest;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Transformers\VehicleTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class VehicleController extends Controller
{
    public function index(IndexVehicleRequest $request): JsonResponse
    {
        $vehicles = QueryBuilder::for(Vehicle::query()->whereBelongsTo($request->user()))
            ->allowedFilters(
                AllowedFilter::callback('archived', function ($query, mixed $value): void {
                    match ($value) {
                        'only' => $query->whereNotNull('archived_at'),
                        'with' => null,
                        default => $query->whereNull('archived_at'),
                    };
                }),
            )
            ->allowedSorts('nickname', 'year', 'make', 'model', 'current_odometer', 'created_at')
            ->defaultSort('nickname', 'id');

        if (! $request->has('filter.archived')) {
            $vehicles->whereNull('archived_at');
        }

        $paginator = $vehicles
            ->paginate($request->integer('page.size', 15), page: $request->integer('page.number', 1))
            ->withQueryString();

        return fractal()
            ->collection($paginator->getCollection(), new VehicleTransformer)
            ->paginateWith(new IlluminatePaginatorAdapter($paginator))
            ->respond();
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = $request->user()->vehicles()->create($request->validated());

        return $this->respondWithItem($vehicle, new VehicleTransformer, 201);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('view', $vehicle);

        return $this->respondWithItem($vehicle, new VehicleTransformer);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $vehicle->update($request->validated());

        return $this->respondWithItem($vehicle->refresh(), new VehicleTransformer);
    }

    public function archive(Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('archive', $vehicle);
        $vehicle->forceFill(['archived_at' => now()])->save();

        return $this->respondWithItem($vehicle->refresh(), new VehicleTransformer);
    }

    public function restore(Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('restore', $vehicle);
        $vehicle->forceFill(['archived_at' => null])->save();

        return $this->respondWithItem($vehicle->refresh(), new VehicleTransformer);
    }
}

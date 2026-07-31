<?php

namespace App\Http\Controllers\Api;

use App\Actions\Maintenance\CreateMaintenanceEvent;
use App\Actions\Maintenance\DeleteMaintenanceEvent;
use App\Actions\Maintenance\UpdateMaintenanceEvent;
use App\Exceptions\InvalidMaintenanceChronologyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexMaintenanceEventRequest;
use App\Http\Requests\StoreMaintenanceEventRequest;
use App\Http\Requests\UpdateMaintenanceEventRequest;
use App\Http\Responses\ApiErrorResponse;
use App\Models\MaintenanceEvent;
use App\Models\Vehicle;
use App\Transformers\MaintenanceEventTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class MaintenanceEventController extends Controller
{
    public function __construct(private CreateMaintenanceEvent $create, private UpdateMaintenanceEvent $update, private DeleteMaintenanceEvent $delete) {}

    public function index(IndexMaintenanceEventRequest $request, Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('view', $vehicle);
        $timezone = $request->user()->timezone;
        $query = QueryBuilder::for(MaintenanceEvent::query()->whereBelongsTo($vehicle)->with('serviceLines.serviceable'))
            ->allowedFilters(
                AllowedFilter::callback('performed_from', fn ($query, mixed $value) => $query->where('performed_at', '>=', Carbon::parse((string) $value, $timezone)->startOfDay()->utc())),
                AllowedFilter::callback('performed_to', fn ($query, mixed $value) => $query->where('performed_at', '<=', Carbon::parse((string) $value, $timezone)->endOfDay()->utc())),
                AllowedFilter::callback('service_type', fn ($query, mixed $value) => $query->whereHas('serviceLines', fn ($lines) => $lines->where('serviceable_type', $value))),
            )
            ->allowedSorts('performed_at', 'odometer', 'total_cost')
            ->defaultSort('-performed_at', '-id');
        $paginator = $query->paginate($request->integer('page.size', 15), page: $request->integer('page.number', 1))->withQueryString();

        return fractal()->collection($paginator->getCollection(), new MaintenanceEventTransformer)
            ->paginateWith(new IlluminatePaginatorAdapter($paginator))->respond();
    }

    public function store(StoreMaintenanceEventRequest $request, Vehicle $vehicle): JsonResponse
    {
        try {
            $event = $this->create->handle($vehicle, $request->validated());
        } catch (InvalidMaintenanceChronologyException $exception) {
            return $this->chronologyError($exception);
        }

        return $this->respondWithItem($event, new MaintenanceEventTransformer, 201);
    }

    public function show(MaintenanceEvent $maintenanceEvent): JsonResponse
    {
        Gate::authorize('view', $maintenanceEvent);

        return $this->respondWithItem($maintenanceEvent->load('serviceLines.serviceable'), new MaintenanceEventTransformer);
    }

    public function update(UpdateMaintenanceEventRequest $request, MaintenanceEvent $maintenanceEvent): JsonResponse
    {
        try {
            $event = $this->update->handle($maintenanceEvent, $request->validated());
        } catch (InvalidMaintenanceChronologyException $exception) {
            return $this->chronologyError($exception);
        }

        return $this->respondWithItem($event, new MaintenanceEventTransformer);
    }

    public function destroy(MaintenanceEvent $maintenanceEvent): JsonResponse
    {
        Gate::authorize('delete', $maintenanceEvent);
        $this->delete->handle($maintenanceEvent);

        return response()->json(status: 204);
    }

    private function chronologyError(InvalidMaintenanceChronologyException $exception): JsonResponse
    {
        return ApiErrorResponse::make(422, 'invalid_maintenance_chronology', $exception->getMessage());
    }
}

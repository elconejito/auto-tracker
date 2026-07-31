<?php

namespace App\Http\Controllers\Api;

use App\Actions\Fuel\CreateFuelEntry;
use App\Actions\Fuel\DeleteFuelEntry;
use App\Actions\Fuel\UpdateFuelEntry;
use App\Exceptions\InvalidFuelChronologyException;
use App\Exceptions\InvalidFuelCostException;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexFuelEntryRequest;
use App\Http\Requests\StoreFuelEntryRequest;
use App\Http\Requests\UpdateFuelEntryRequest;
use App\Http\Responses\ApiErrorResponse;
use App\Models\FuelEntry;
use App\Models\Vehicle;
use App\Transformers\FuelEntryTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class FuelEntryController extends Controller
{
    public function __construct(
        private CreateFuelEntry $createFuelEntry,
        private UpdateFuelEntry $updateFuelEntry,
        private DeleteFuelEntry $deleteFuelEntry,
    ) {}

    public function index(IndexFuelEntryRequest $request, Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('view', $vehicle);
        $timezone = $request->user()->timezone;

        $entries = QueryBuilder::for(FuelEntry::query()->forVehicle($vehicle))
            ->allowedFilters(
                AllowedFilter::callback('fueled_from', fn ($query, mixed $value) => $query->where(
                    'fueled_at',
                    '>=',
                    Carbon::parse((string) $value, $timezone)->startOfDay()->utc(),
                )),
                AllowedFilter::callback('fueled_to', fn ($query, mixed $value) => $query->where(
                    'fueled_at',
                    '<=',
                    Carbon::parse((string) $value, $timezone)->endOfDay()->utc(),
                )),
                AllowedFilter::callback('odometer_min', fn ($query, mixed $value) => $query->where('odometer', '>=', $value)),
                AllowedFilter::callback('odometer_max', fn ($query, mixed $value) => $query->where('odometer', '<=', $value)),
                AllowedFilter::callback('mpg_min', fn ($query, mixed $value) => $query->whereNotNull('calculated_mpg')->where('calculated_mpg', '>=', $value)),
                AllowedFilter::callback('mpg_max', fn ($query, mixed $value) => $query->whereNotNull('calculated_mpg')->where('calculated_mpg', '<=', $value)),
            )
            ->allowedSorts('fueled_at', 'odometer', 'gallons', 'calculated_mpg', 'total_cost')
            ->defaultSort('-fueled_at', '-id');

        $paginator = $entries
            ->paginate($request->integer('page.size', 15), page: $request->integer('page.number', 1))
            ->withQueryString();

        return fractal()
            ->collection($paginator->getCollection(), new FuelEntryTransformer)
            ->paginateWith(new IlluminatePaginatorAdapter($paginator))
            ->respond();
    }

    public function store(StoreFuelEntryRequest $request, Vehicle $vehicle): JsonResponse
    {
        try {
            $fuelEntry = $this->createFuelEntry->handle($vehicle, $request->validated());
        } catch (InvalidFuelChronologyException|InvalidFuelCostException $exception) {
            return $this->domainError($exception);
        }

        return $this->respondWithItem($fuelEntry, new FuelEntryTransformer, 201);
    }

    public function show(FuelEntry $fuelEntry): JsonResponse
    {
        Gate::authorize('view', $fuelEntry);

        return $this->respondWithItem($fuelEntry, new FuelEntryTransformer);
    }

    public function update(UpdateFuelEntryRequest $request, FuelEntry $fuelEntry): JsonResponse
    {
        try {
            $fuelEntry = $this->updateFuelEntry->handle($fuelEntry, $request->validated());
        } catch (InvalidFuelChronologyException|InvalidFuelCostException $exception) {
            return $this->domainError($exception);
        }

        return $this->respondWithItem($fuelEntry, new FuelEntryTransformer);
    }

    public function destroy(FuelEntry $fuelEntry): JsonResponse
    {
        Gate::authorize('delete', $fuelEntry);
        $this->deleteFuelEntry->handle($fuelEntry);

        return response()->json(status: 204);
    }

    private function domainError(InvalidFuelChronologyException|InvalidFuelCostException $exception): JsonResponse
    {
        return ApiErrorResponse::make(
            status: 422,
            code: $exception instanceof InvalidFuelChronologyException
                ? 'invalid_fuel_chronology'
                : 'invalid_fuel_cost',
            detail: $exception->getMessage(),
        );
    }
}

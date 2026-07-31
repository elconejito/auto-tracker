<?php

namespace App\Queries;

use App\Models\FuelEntry;
use App\Models\MaintenanceEvent;
use App\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class VehicleTimeline
{
    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function paginate(Vehicle $vehicle, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $fuel = DB::table('fuel_entries')
            ->where('vehicle_id', $vehicle->getKey())
            ->select(['id', 'fueled_at as occurred_at', 'odometer'])
            ->selectRaw("'fuel' as kind");
        $maintenance = DB::table('maintenance_events')
            ->where('vehicle_id', $vehicle->getKey())
            ->select(['id', 'performed_at as occurred_at', 'odometer'])
            ->selectRaw("'maintenance' as kind");

        $paginator = DB::query()->fromSub($fuel->unionAll($maintenance), 'timeline')
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate($perPage, page: $page);

        $fuelIds = $paginator->getCollection()->where('kind', 'fuel')->pluck('id');
        $maintenanceIds = $paginator->getCollection()->where('kind', 'maintenance')->pluck('id');
        $fuelEntries = FuelEntry::query()->whereKey($fuelIds)->get()->keyBy('id');
        $events = MaintenanceEvent::query()->with('serviceLines.serviceable')->whereKey($maintenanceIds)->get()->keyBy('id');

        $paginator->setCollection($paginator->getCollection()->map(function (object $row) use ($fuelEntries, $events): array {
            if ($row->kind === 'fuel') {
                $entry = $fuelEntries->get($row->id);

                return [
                    'id' => (string) $row->id,
                    'occurred_at' => $entry->fueled_at?->toAtomString(),
                    'odometer' => $entry->odometer,
                    'kind' => 'fuel',
                    'title' => 'Fuel fill-up',
                    'summary' => $entry->station,
                    'cost' => $entry->total_cost,
                    'details' => ['gallons' => $entry->gallons, 'calculated_mpg' => $entry->calculated_mpg],
                ];
            }

            $event = $events->get($row->id);

            return [
                'id' => (string) $row->id,
                'occurred_at' => $event->performed_at?->toAtomString(),
                'odometer' => $event->odometer,
                'kind' => 'maintenance',
                'title' => 'Maintenance visit',
                'summary' => $event->provider,
                'cost' => $event->total_cost,
                'details' => [
                    'services' => $event->serviceLines->map(fn ($line): array => [
                        'type' => $line->serviceable_type,
                        'label' => $line->serviceable->label(),
                    ])->all(),
                ],
            ];
        }));

        return $paginator;
    }
}

<?php

namespace App\Actions\Dashboard;

use App\Models\MaintenanceEvent;
use App\Models\Vehicle;
use App\Queries\VehicleServiceRecency;
use App\Queries\VehicleTimeline;

final class BuildVehicleDashboard
{
    public function __construct(private VehicleServiceRecency $recency, private VehicleTimeline $timeline) {}

    /** @return array<string, mixed> */
    public function handle(Vehicle $vehicle): array
    {
        $vehicle = Vehicle::query()->withFuelSummaries()->findOrFail($vehicle->getKey());
        $latestMaintenance = MaintenanceEvent::query()->whereBelongsTo($vehicle)
            ->latest('performed_at')->latest('id')->first();

        return [
            'vehicle' => [
                'id' => (string) $vehicle->getKey(),
                'nickname' => $vehicle->nickname,
                'current_odometer' => $vehicle->current_odometer,
                'archived_at' => $vehicle->archived_at?->toAtomString(),
            ],
            'fuel_summary' => [
                'latest_mpg' => $vehicle->getAttribute('latest_mpg'),
                'average_mpg' => $vehicle->getAttribute('average_mpg'),
                'most_recent_fueled_at' => $vehicle->getAttribute('most_recent_fueled_at')?->toAtomString(),
            ],
            'maintenance_summary' => [
                'most_recent_performed_at' => $latestMaintenance?->performed_at?->toAtomString(),
                'most_recent_odometer' => $latestMaintenance?->odometer,
            ],
            'service_recency' => $this->recency->forVehicle($vehicle),
            'recent_timeline' => $this->timeline->paginate($vehicle, 5)->items(),
        ];
    }
}

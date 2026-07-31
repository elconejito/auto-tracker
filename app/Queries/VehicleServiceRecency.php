<?php

namespace App\Queries;

use App\Actions\Maintenance\ServiceCatalog;
use App\Models\AbstractService;
use App\Models\MaintenanceEvent;
use App\Models\ServiceLine;
use App\Models\Vehicle;

final class VehicleServiceRecency
{
    /** @var list<string> */
    private const AXLE_SERVICE_TYPES = [
        'brake_pad_replacement',
        'brake_rotor_replacement',
        'tire_replacement',
    ];

    /** @var list<string> */
    private const AXLES = ['front', 'rear'];

    /**
     * @return list<array{key: string, type: string, label: string, applicability: string, performed_at: ?string, odometer: ?int, miles_since: ?int}>
     */
    public function forVehicle(Vehicle $vehicle): array
    {
        $latestByKey = [];

        $eventIds = MaintenanceEvent::query()
            ->whereBelongsTo($vehicle)
            ->select('id');

        $serviceLines = ServiceLine::query()
            ->select(['id', 'maintenance_event_id', 'serviceable_type', 'serviceable_id'])
            ->whereIn('maintenance_event_id', $eventIds)
            ->with([
                'maintenanceEvent:id,vehicle_id,performed_at,odometer',
                'serviceable',
            ])
            ->get();

        foreach ($serviceLines as $serviceLine) {
            $service = $serviceLine->serviceable;
            $event = $serviceLine->maintenanceEvent;

            if (! $service instanceof AbstractService || ! $event instanceof MaintenanceEvent || ! $service->supportsMileageSince()) {
                continue;
            }

            $type = (string) $serviceLine->serviceable_type;

            if (in_array($type, self::AXLE_SERVICE_TYPES, true)) {
                $applicability = $this->normalizeApplicability($service->getAttribute('position'));

                if ($applicability === null) {
                    continue;
                }

                $applicableAxles = $applicability === 'all' ? self::AXLES : [$applicability];

                foreach ($applicableAxles as $axle) {
                    $key = $this->keyFor($type, $axle);

                    if (! isset($latestByKey[$key]) || $this->eventIsLater($event, $latestByKey[$key]['event'])) {
                        $latestByKey[$key] = ['service' => $service, 'event' => $event];
                    }
                }

                continue;
            }

            if (! isset($latestByKey[$type]) || $this->eventIsLater($event, $latestByKey[$type]['event'])) {
                $latestByKey[$type] = ['service' => $service, 'event' => $event];
            }
        }

        return collect($this->definitions())
            ->map(function (array $definition) use ($latestByKey, $vehicle): array {
                $latest = $latestByKey[$definition['key']] ?? null;
                $event = $latest['event'] ?? null;

                return [
                    'key' => $definition['key'],
                    'type' => $definition['type'],
                    'label' => $definition['label'],
                    'applicability' => $definition['applicability'],
                    'performed_at' => $event?->performed_at?->toAtomString(),
                    'odometer' => $event?->odometer === null ? null : (int) $event->odometer,
                    'miles_since' => $event === null ? null : (int) $vehicle->current_odometer - (int) $event->odometer,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, type: string, label: string, applicability: string}>
     */
    private function definitions(): array
    {
        $definitions = [];

        foreach (ServiceCatalog::aliases() as $type) {
            $serviceClass = ServiceCatalog::modelFor($type);
            $service = new $serviceClass;

            if (! $service->supportsMileageSince()) {
                continue;
            }

            if (in_array($type, self::AXLE_SERVICE_TYPES, true)) {
                foreach (self::AXLES as $axle) {
                    $definitions[] = [
                        'key' => $this->keyFor($type, $axle),
                        'type' => $type,
                        'label' => ucfirst($axle).' '.$service->label(),
                        'applicability' => $axle,
                    ];
                }

                continue;
            }

            $definitions[] = [
                'key' => $type,
                'type' => $type,
                'label' => $service->label(),
                'applicability' => 'all',
            ];
        }

        return $definitions;
    }

    private function keyFor(string $type, string $axle): string
    {
        return $type.'_'.$axle;
    }

    private function eventIsLater(MaintenanceEvent $candidate, MaintenanceEvent $current): bool
    {
        if ($candidate->performed_at->notEqualTo($current->performed_at)) {
            return $candidate->performed_at->greaterThan($current->performed_at);
        }

        return strcmp((string) $candidate->getKey(), (string) $current->getKey()) > 0;
    }

    private function normalizeApplicability(mixed $position): ?string
    {
        return match ($position) {
            'all' => 'all',
            'front', 'front_left', 'front_right' => 'front',
            'rear', 'rear_left', 'rear_right' => 'rear',
            default => null,
        };
    }
}

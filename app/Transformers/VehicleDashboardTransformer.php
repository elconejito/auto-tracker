<?php

namespace App\Transformers;

use League\Fractal\TransformerAbstract;

final class VehicleDashboardTransformer extends TransformerAbstract
{
    /** @param array<string, mixed> $dashboard */
    public function transform(array $dashboard): array
    {
        return ['id' => $dashboard['vehicle']['id'], 'type' => 'vehicle_dashboards', 'attributes' => $dashboard];
    }
}

<?php

namespace App\Transformers;

use App\Models\ServiceLine;
use League\Fractal\TransformerAbstract;

final class ServiceLineTransformer extends TransformerAbstract
{
    public function transform(ServiceLine $line): array
    {
        $service = $line->serviceable;

        return [
            'id' => (string) $line->getKey(),
            'type' => $service->getMorphClass(),
            'label' => $service->label(),
            'line_cost' => $line->line_cost,
            'details' => collect($service->getAttributes())
                ->except(['id', 'created_at', 'updated_at'])
                ->all(),
        ];
    }
}

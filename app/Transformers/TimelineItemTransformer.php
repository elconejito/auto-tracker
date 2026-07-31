<?php

namespace App\Transformers;

use League\Fractal\TransformerAbstract;

final class TimelineItemTransformer extends TransformerAbstract
{
    /** @param array<string, mixed> $item */
    public function transform(array $item): array
    {
        return ['id' => $item['id'], 'type' => 'timeline_items', 'attributes' => collect($item)->except('id')->all()];
    }
}

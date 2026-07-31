<?php

namespace App\Transformers;

use App\Models\User;
use League\Fractal\TransformerAbstract;

final class UserTransformer extends TransformerAbstract
{
    /**
     * List of resources to automatically include
     */
    protected array $defaultIncludes = [
        //
    ];

    /**
     * List of resources possible to include
     */
    protected array $availableIncludes = [
        //
    ];

    /**
     * @return array{id: string, type: string, attributes: array{name: string, email: string, timezone: string, created_at: string|null}}
     */
    public function transform(User $user): array
    {
        return [
            'id' => (string) $user->getKey(),
            'type' => 'users',
            'attributes' => [
                'name' => $user->name,
                'email' => $user->email,
                'timezone' => $user->timezone,
                'created_at' => $user->created_at?->toAtomString(),
            ],
        ];
    }
}

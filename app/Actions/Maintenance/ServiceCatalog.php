<?php

namespace App\Actions\Maintenance;

use App\Models\AbstractService;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

final class ServiceCatalog
{
    /** @return list<string> */
    public static function aliases(): array
    {
        return array_keys(Relation::morphMap());
    }

    /** @return class-string<AbstractService> */
    public static function modelFor(string $alias): string
    {
        $model = Relation::getMorphedModel($alias);

        if (! is_string($model) || ! is_subclass_of($model, AbstractService::class)) {
            throw new InvalidArgumentException("Unknown maintenance service type [{$alias}].");
        }

        return $model;
    }
}

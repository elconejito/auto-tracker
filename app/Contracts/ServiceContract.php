<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphOne;

interface ServiceContract
{
    public function serviceLine(): MorphOne;

    public function label(): string;

    public function recencyGroup(): string;

    public function supportsMileageSince(): bool;
}

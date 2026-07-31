<?php

namespace App\Models\Services;

use App\Models\AbstractService;

class TireRotation extends AbstractService
{
    protected static string $serviceLabel = 'Tire rotation';

    protected static string $serviceRecencyGroup = 'tires';

    /** @var list<string> */
    protected $fillable = ['pattern', 'notes'];
}

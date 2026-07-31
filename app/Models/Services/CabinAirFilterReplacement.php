<?php

namespace App\Models\Services;

use App\Models\AbstractService;

class CabinAirFilterReplacement extends AbstractService
{
    protected static string $serviceLabel = 'Cabin air filter replacement';

    protected static string $serviceRecencyGroup = 'filters';

    /** @var list<string> */
    protected $fillable = ['manufacturer', 'part_number'];
}

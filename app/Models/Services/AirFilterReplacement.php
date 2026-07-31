<?php

namespace App\Models\Services;

use App\Models\AbstractService;

class AirFilterReplacement extends AbstractService
{
    protected static string $serviceLabel = 'Air filter replacement';

    protected static string $serviceRecencyGroup = 'filters';

    /** @var list<string> */
    protected $fillable = ['manufacturer', 'part_number'];
}

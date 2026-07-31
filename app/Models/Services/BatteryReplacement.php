<?php

namespace App\Models\Services;

use App\Models\AbstractService;

class BatteryReplacement extends AbstractService
{
    protected static string $serviceLabel = 'Battery replacement';

    protected static string $serviceRecencyGroup = 'battery';

    /** @var list<string> */
    protected $fillable = ['manufacturer', 'part_number'];
}

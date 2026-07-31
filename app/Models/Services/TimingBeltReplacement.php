<?php

namespace App\Models\Services;

use App\Models\AbstractService;

class TimingBeltReplacement extends AbstractService
{
    protected static string $serviceLabel = 'Timing belt replacement';

    protected static string $serviceRecencyGroup = 'belts';

    /** @var list<string> */
    protected $fillable = ['manufacturer', 'part_number'];
}

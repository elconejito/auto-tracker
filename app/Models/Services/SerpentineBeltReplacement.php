<?php

namespace App\Models\Services;

use App\Models\AbstractService;

class SerpentineBeltReplacement extends AbstractService
{
    protected static string $serviceLabel = 'Serpentine belt replacement';

    protected static string $serviceRecencyGroup = 'belts';

    /** @var list<string> */
    protected $fillable = ['manufacturer', 'part_number'];
}

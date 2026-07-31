<?php

namespace App\Models\Services;

use App\Models\AbstractService;

class SparkPlugReplacement extends AbstractService
{
    protected static string $serviceLabel = 'Spark plug replacement';

    protected static string $serviceRecencyGroup = 'spark_plugs';

    /** @var list<string> */
    protected $fillable = ['manufacturer', 'part_number'];
}

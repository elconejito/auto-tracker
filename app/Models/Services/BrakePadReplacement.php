<?php

namespace App\Models\Services;

use App\Models\AbstractService;

class BrakePadReplacement extends AbstractService
{
    protected static string $serviceLabel = 'Brake pad replacement';

    protected static string $serviceRecencyGroup = 'brakes';

    /** @var list<string> */
    protected $fillable = ['position', 'manufacturer', 'part_number'];
}

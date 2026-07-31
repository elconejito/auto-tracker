<?php

namespace App\Models\Services;

use App\Models\AbstractService;

class VehicleInspection extends AbstractService
{
    protected static string $serviceLabel = 'Vehicle inspection';

    protected static string $serviceRecencyGroup = 'inspection';

    /** @var list<string> */
    protected $fillable = ['notes'];
}

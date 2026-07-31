<?php

namespace App\Models\Services;

use App\Models\AbstractService;

class CoolantService extends AbstractService
{
    protected static string $serviceLabel = 'Coolant service';

    protected static string $serviceRecencyGroup = 'coolant';

    /** @var list<string> */
    protected $fillable = ['notes'];
}

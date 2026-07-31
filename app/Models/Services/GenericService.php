<?php

namespace App\Models\Services;

use App\Models\AbstractService;

class GenericService extends AbstractService
{
    protected static string $serviceLabel = 'Generic service';

    protected static string $serviceRecencyGroup = 'generic';

    /** @var list<string> */
    protected $fillable = ['name', 'description'];
}

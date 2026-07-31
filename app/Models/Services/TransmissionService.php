<?php

namespace App\Models\Services;

use App\Models\AbstractService;

class TransmissionService extends AbstractService
{
    protected static string $serviceLabel = 'Transmission service';

    protected static string $serviceRecencyGroup = 'transmission';

    /** @var list<string> */
    protected $fillable = ['notes'];
}

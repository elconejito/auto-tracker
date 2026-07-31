<?php

namespace App\Models\Services;

use App\Models\AbstractService;

class TireReplacement extends AbstractService
{
    protected static string $serviceLabel = 'Tire replacement';

    protected static string $serviceRecencyGroup = 'tires';

    /** @var list<string> */
    protected $fillable = ['position', 'manufacturer', 'model', 'size', 'quantity'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }
}

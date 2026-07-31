<?php

namespace App\Models\Services;

use App\Models\AbstractService;

class OilChange extends AbstractService
{
    protected static string $serviceLabel = 'Oil change';

    protected static string $serviceRecencyGroup = 'oil_change';

    /** @var list<string> */
    protected $fillable = ['viscosity', 'quantity', 'filter_replaced', 'filter_part_number'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'filter_replaced' => 'boolean'];
    }
}

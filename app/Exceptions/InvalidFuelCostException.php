<?php

namespace App\Exceptions;

use RuntimeException;

final class InvalidFuelCostException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Total cost and price per gallon must agree within one cent.');
    }
}

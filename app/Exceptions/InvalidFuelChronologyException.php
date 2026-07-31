<?php

namespace App\Exceptions;

use RuntimeException;

final class InvalidFuelChronologyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Fuel entries must have strictly increasing odometer readings in chronological order.');
    }
}

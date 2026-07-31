<?php

namespace Tests\Unit\Actions\Fuel;

use App\Actions\Fuel\FuelDecimal;
use PHPUnit\Framework\TestCase;

class FuelDecimalTest extends TestCase
{
    public function test_fixed_point_helpers_round_without_float_persistence(): void
    {
        $this->assertSame(3500, FuelDecimal::toScaledInt('3.500', 3));
        $this->assertSame(350, FuelDecimal::toScaledInt('3.50', 2));
        $this->assertSame('35.01', FuelDecimal::formatScaledInt(3501, 2));
        $this->assertSame(3000, FuelDecimal::mpgHundredths(300, 10000));
        $this->assertSame(3500, FuelDecimal::roundProductToCents(3500, 10000));
    }
}

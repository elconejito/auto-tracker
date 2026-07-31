<?php

namespace App\Actions\Fuel;

use InvalidArgumentException;

final class FuelDecimal
{
    public static function toScaledInt(string|int|float|null $value, int $scale): ?int
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException('Fuel decimal values must be non-negative decimal numbers.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad($fraction, $scale + 1, '0');
        $roundUp = (int) ($fraction[$scale] ?? '0') >= 5;
        $scaled = ((int) $whole * (10 ** $scale)) + (int) substr($fraction, 0, $scale);

        return $roundUp ? $scaled + 1 : $scaled;
    }

    public static function formatScaledInt(?int $value, int $scale): ?string
    {
        if ($value === null) {
            return null;
        }

        $factor = 10 ** $scale;
        $whole = intdiv($value, $factor);
        $fraction = str_pad((string) ($value % $factor), $scale, '0', STR_PAD_LEFT);

        return $scale === 0 ? (string) $whole : $whole.'.'.$fraction;
    }

    public static function roundProductToCents(int $leftMilli, int $rightMilli): int
    {
        return intdiv(($leftMilli * $rightMilli) + 5000, 10000);
    }

    public static function priceMilliFromTotalCents(int $totalCents, int $gallonsMilli): int
    {
        return intdiv(($totalCents * 10000) + intdiv($gallonsMilli, 2), $gallonsMilli);
    }

    public static function mpgHundredths(int $miles, int $gallonsMilli): int
    {
        return intdiv(($miles * 100000) + intdiv($gallonsMilli, 2), $gallonsMilli);
    }
}

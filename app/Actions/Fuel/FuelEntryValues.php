<?php

namespace App\Actions\Fuel;

use App\Exceptions\InvalidFuelCostException;

final class FuelEntryValues
{
    /**
     * @param  array<string, mixed>  $values
     * @return array{gallons: string, total_cost: string|null, price_per_gallon: string|null}
     */
    public static function normalize(array $values): array
    {
        $gallonsMilli = FuelDecimal::toScaledInt($values['gallons'], 3);
        $totalCents = FuelDecimal::toScaledInt($values['total_cost'] ?? null, 2);
        $priceMilli = FuelDecimal::toScaledInt($values['price_per_gallon'] ?? null, 3);

        if ($gallonsMilli === null || $gallonsMilli <= 0) {
            throw new InvalidFuelCostException;
        }

        if ($totalCents !== null && $priceMilli !== null) {
            $derivedTotalCents = FuelDecimal::roundProductToCents($priceMilli, $gallonsMilli);

            if (abs($derivedTotalCents - $totalCents) > 1) {
                throw new InvalidFuelCostException;
            }
        } elseif ($totalCents !== null) {
            $priceMilli = FuelDecimal::priceMilliFromTotalCents($totalCents, $gallonsMilli);
        } elseif ($priceMilli !== null) {
            $totalCents = FuelDecimal::roundProductToCents($priceMilli, $gallonsMilli);
        }

        return [
            'gallons' => FuelDecimal::formatScaledInt($gallonsMilli, 3),
            'total_cost' => FuelDecimal::formatScaledInt($totalCents, 2),
            'price_per_gallon' => FuelDecimal::formatScaledInt($priceMilli, 3),
        ];
    }
}

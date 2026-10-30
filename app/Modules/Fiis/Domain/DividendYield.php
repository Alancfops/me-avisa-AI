<?php

namespace App\Modules\Fiis\Domain;

use Brick\Math\BigDecimal;

final class DividendYield
{
    private const MONTHS = 12;

    /**
     * Só exibição: nenhuma regra usa este número. Com menos de 12 meses não há "12m".
     *
     * @param  list<MonthlyYield>  $yields
     * @return string|null fração
     */
    public static function trailing12Months(array $yields, string $day): ?string
    {
        $delivered = array_values(array_filter($yields, fn (MonthlyYield $yield): bool => $yield->deliveredOn <= $day));

        if (count($delivered) < self::MONTHS) {
            return null;
        }

        usort($delivered, fn (MonthlyYield $a, MonthlyYield $b): int => $b->month <=> $a->month);
        $sum = BigDecimal::zero();

        foreach (array_slice($delivered, 0, self::MONTHS) as $yield) {
            $sum = $sum->plus($yield->fraction);
        }

        return (string) $sum->strippedOfTrailingZeros();
    }
}

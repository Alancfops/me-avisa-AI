<?php

namespace App\Modules\Fiis\Domain;

/**
 * Rendimento estimado: VP × fração do rendimento, ambos do informe da mesma competência.
 * A CVM não traz o valor pago por cota, então isto é uma estimativa, não o pago.
 */
final class EstimatedDividends
{
    public const MONTHS = 12;

    /**
     * @param  list<BookValueReport>  $bookValues
     * @param  list<MonthlyYield>  $yields
     * @return list<MonthlyDividend> da competência mais antiga para a mais nova
     */
    public static function lastTwelve(array $bookValues, array $yields, string $day): array
    {
        $fractions = [];

        foreach ($yields as $yield) {
            if ($yield->deliveredOn <= $day) {
                $fractions[$yield->month] = $yield->fraction;
            }
        }

        $dividends = [];

        foreach ($bookValues as $report) {
            if ($report->deliveredOn <= $day && isset($fractions[$report->month])) {
                $dividends[$report->month] = new MonthlyDividend(
                    $report->month,
                    $report->bookValue->times($fractions[$report->month]),
                );
            }
        }

        ksort($dividends);

        return array_slice(array_values($dividends), -self::MONTHS);
    }
}

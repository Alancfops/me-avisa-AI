<?php

namespace App\Modules\Fiis\Domain;

/** Contas do histórico de fechamentos para a página do fundo. Datas são `Y-m-d`, sem relógio. */
final class PriceHistory
{
    public const POINTS_MONTHS = 12;

    /**
     * Média dos fechamentos da janela, só quando o histórico cobre a janela inteira:
     * média de janela incompleta enganaria como se fosse de 6 ou 12 meses.
     *
     * @param  list<DailyClose>  $closes
     */
    public static function average(array $closes, string $day, int $months): WindowAverage
    {
        $earlier = self::before($closes, $day);

        if ($earlier === []) {
            return new WindowAverage($months, 0, null);
        }

        $monthsStored = min($months, self::wholeMonthsBetween($earlier[0]->date, $day));

        if ($monthsStored < $months) {
            return new WindowAverage($months, $monthsStored, null);
        }

        // Em texto, `2026-02-31` não existe, mas serve de limite de comparação.
        $from = self::monthsBefore($day, $months);
        $inWindow = array_filter($earlier, fn (DailyClose $close): bool => $close->date >= $from);

        return new WindowAverage($months, $monthsStored, Money::average(
            array_map(fn (DailyClose $close): Money => $close->close, array_values($inWindow)),
        ));
    }

    /**
     * Fechamentos dos últimos 12 meses antes de `$day`, cada um com o P/VP do informe
     * que valia naquele dia.
     *
     * @param  list<DailyClose>  $closes
     * @param  list<BookValueReport>  $reports
     * @return list<PricePoint>
     */
    public static function points(array $closes, array $reports, string $day): array
    {
        $from = self::monthsBefore($day, self::POINTS_MONTHS);
        $points = [];

        foreach (self::before($closes, $day) as $close) {
            if ($close->date < $from) {
                continue;
            }

            $report = FundSnapshot::reportInForce($reports, $close->date);
            $points[] = new PricePoint(
                $close->date,
                $close->close,
                $report === null ? null : Ratio::of($close->close, $report->bookValue),
            );
        }

        return $points;
    }

    /**
     * @param  list<DailyClose>  $closes
     * @return list<DailyClose> em ordem de data
     */
    private static function before(array $closes, string $day): array
    {
        $earlier = array_values(array_filter($closes, fn (DailyClose $close): bool => $close->date < $day));
        usort($earlier, fn (DailyClose $a, DailyClose $b): int => $a->date <=> $b->date);

        return $earlier;
    }

    private static function monthsBefore(string $day, int $months): string
    {
        $index = (int) substr($day, 0, 4) * 12 + (int) substr($day, 5, 2) - 1 - $months;

        return sprintf('%04d-%02d', intdiv($index, 12), $index % 12 + 1).substr($day, 7);
    }

    private static function wholeMonthsBetween(string $from, string $to): int
    {
        $months = ((int) substr($to, 0, 4) - (int) substr($from, 0, 4)) * 12
            + (int) substr($to, 5, 2) - (int) substr($from, 5, 2);

        return substr($to, 8, 2) < substr($from, 8, 2) ? $months - 1 : $months;
    }
}

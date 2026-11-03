<?php

namespace App\Modules\Fiis\Domain;

/** Contas do histórico de fechamentos para a página do fundo. Datas são `Y-m-d`, sem relógio. */
final class PriceHistory
{
    public const POINTS_MONTHS = 12;

    /**
     * Quantos dias o primeiro fechamento guardado pode vir depois do início da janela
     * e ela ainda contar como completa: o início cai em fim de semana ou feriado, e o
     * carnaval junta até 5 dias sem pregão. O front espelha este número em
     * `GAP_TOLERANCE_DAYS` (resources/js/lib/history.ts) para dizer o mesmo que o card.
     */
    public const WINDOW_START_TOLERANCE_DAYS = 5;

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

        $from = self::monthsBefore($day, $months);

        if (TradingDays::calendarDaysBetween($from, $earlier[0]->date) > self::WINDOW_START_TOLERANCE_DAYS) {
            // Incompleta nunca mostra o total, mesmo que os meses inteiros cheguem a ele.
            $whole = min($months - 1, self::wholeMonthsBetween($earlier[0]->date, $day));

            return new WindowAverage($months, $whole, null);
        }

        $monthsStored = $months;
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

    /** Sem estourar o fim do mês: 31/08 menos 6 meses é 28/02, como no front. */
    private static function monthsBefore(string $day, int $months): string
    {
        $index = (int) substr($day, 0, 4) * 12 + (int) substr($day, 5, 2) - 1 - $months;
        $year = intdiv($index, 12);
        $month = $index % 12 + 1;
        $leap = ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
        $daysInMonth = match ($month) {
            2 => $leap ? 29 : 28,
            4, 6, 9, 11 => 30,
            default => 31,
        };

        return sprintf('%04d-%02d-%02d', $year, $month, min((int) substr($day, 8, 2), $daysInMonth));
    }

    private static function wholeMonthsBetween(string $from, string $to): int
    {
        $months = ((int) substr($to, 0, 4) - (int) substr($from, 0, 4)) * 12
            + (int) substr($to, 5, 2) - (int) substr($from, 5, 2);

        return substr($to, 8, 2) < substr($from, 8, 2) ? $months - 1 : $months;
    }
}

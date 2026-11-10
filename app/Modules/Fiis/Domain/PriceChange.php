<?php

namespace App\Modules\Fiis\Domain;

/** Variação do preço desde N dias corridos atrás. Datas `Y-m-d`, sem relógio. */
final class PriceChange
{
    /**
     * A base é o último fechamento em ou antes do dia-alvo (`$day` menos `$calendarDays`):
     * se o alvo caiu em fim de semana ou feriado, vale o pregão anterior. Base mais de
     * `WINDOW_START_TOLERANCE_DAYS` antes do alvo é histórico que não cobre o período: `null`.
     *
     * @param  list<DailyClose>  $closes  em ordem crescente de data
     */
    public static function since(array $closes, Money $current, string $day, int $calendarDays): ?Percentage
    {
        $base = null;

        foreach ($closes as $close) {
            if (TradingDays::calendarDaysBetween($close->date, $day) < $calendarDays) {
                break;
            }

            $base = $close;
        }

        if ($base === null) {
            return null;
        }

        $daysBeforeTarget = TradingDays::calendarDaysBetween($base->date, $day) - $calendarDays;

        return $daysBeforeTarget > PriceHistory::WINDOW_START_TOLERANCE_DAYS
            ? null
            : Percentage::change($base->close, $current);
    }
}

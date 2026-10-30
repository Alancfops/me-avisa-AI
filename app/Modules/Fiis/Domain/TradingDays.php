<?php

namespace App\Modules\Fiis\Domain;

/** Conta dias úteis sem calendário de feriados, que está fora do escopo do módulo. */
final class TradingDays
{
    /** 01/01/1970 foi quinta-feira: é a quarta posição a partir de segunda. */
    private const FIRST_DAY_WEEKDAY_OFFSET = 3;

    /** Dias de semana estritamente entre dois dias `Y-m-d`. */
    public static function missingBetween(string $from, string $to): int
    {
        $missing = 0;

        for ($day = self::dayNumber($from) + 1, $last = self::dayNumber($to); $day < $last; $day++) {
            if (($day + self::FIRST_DAY_WEEKDAY_OFFSET) % 7 < 5) {
                $missing++;
            }
        }

        return $missing;
    }

    /** Dias desde 01/01/1970. O Domain não usa relógio nem classes de data do PHP. */
    private static function dayNumber(string $day): int
    {
        [$year, $month, $date] = array_map(intval(...), explode('-', $day));
        $year -= $month <= 2 ? 1 : 0;
        $era = (int) floor($year / 400);
        $yearOfEra = $year - $era * 400;
        $dayOfYear = (int) floor((153 * ($month + ($month > 2 ? -3 : 9)) + 2) / 5) + $date - 1;
        $dayOfEra = $yearOfEra * 365 + (int) floor($yearOfEra / 4) - (int) floor($yearOfEra / 100) + $dayOfYear;

        return $era * 146097 + $dayOfEra - 719468;
    }
}

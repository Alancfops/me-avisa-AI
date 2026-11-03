<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\PriceHistory;
use DateTimeImmutable;
use DateTimeZone;

/** O dia do mercado é o de São Paulo, seja qual for o fuso do horário recebido. */
final class MarketTime
{
    public const ZONE = 'America/Sao_Paulo';

    public static function local(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone(new DateTimeZone(self::ZONE));
    }

    public static function day(DateTimeImmutable $moment): string
    {
        return self::local($moment)->format('Y-m-d');
    }

    /** Com data real: `2028-02-29` menos um ano não existe e o Postgres recusaria. */
    public static function yearBefore(string $day): string
    {
        return (new DateTimeImmutable($day))->modify('-1 year')->format('Y-m-d');
    }

    /** `yearBefore` com a folga que o `PriceHistory` aceita no início da janela. */
    public static function yearWindowFetchStart(string $day): string
    {
        $days = PriceHistory::WINDOW_START_TOLERANCE_DAYS;

        return (new DateTimeImmutable(self::yearBefore($day)))->modify("-{$days} days")->format('Y-m-d');
    }
}

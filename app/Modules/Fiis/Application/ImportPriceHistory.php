<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\DailyClose;

final readonly class ImportPriceHistory
{
    /**
     * Depois do fim do pregão e do leilão de fechamento, a barra de hoje já é um
     * fechamento. Antes disso, é o preço ao vivo com outro nome.
     */
    public const DAY_CLOSED_AT = '19:00';

    public function __construct(
        private Quotes $quotes,
        private MarketData $market,
        private Clock $clock,
    ) {}

    /** @throws SourceUnavailable */
    public function handle(string $ticker): void
    {
        $now = MarketTime::local($this->clock->now());
        $today = $now->format('Y-m-d');
        $todayIsClosed = $now->format('H:i') >= self::DAY_CLOSED_AT;

        $closes = array_filter(
            $this->quotes->history($ticker),
            fn (DailyClose $close): bool => $close->date < $today || ($todayIsClosed && $close->date === $today),
        );

        $this->market->saveCloses($ticker, array_values($closes));
    }
}

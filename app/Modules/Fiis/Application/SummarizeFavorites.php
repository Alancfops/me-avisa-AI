<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\EstimatedDividends;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\MonthlyDividend;
use App\Modules\Fiis\Domain\Percentage;
use App\Modules\Fiis\Domain\PriceChange;
use DateTimeImmutable;

final readonly class SummarizeFavorites
{
    private const SHORT_DAYS = 7;

    private const LONG_DAYS = 30;

    /** Os 30 dias da variação longa mais a folga de fim de semana e feriado do `PriceChange`. */
    private const CLOSES_WINDOW_DAYS = 40;

    public function __construct(
        private FavoriteRepository $favorites,
        private DescribeFund $describe,
        private MarketData $market,
        private ReportRepository $reports,
        private ListAlerts $alerts,
        private Clock $clock,
    ) {}

    /** @return list<FavoriteSummary> lista leve: sem o detalhe que só a página do fundo usa */
    public function all(): array
    {
        $summaries = [];

        foreach ($this->favorites->tickers() as $ticker) {
            $view = $this->describe->handle($ticker);

            if ($view !== null) {
                $summaries[] = $this->summarize($view);
            }
        }

        return $summaries;
    }

    /** @return FavoriteSummary|null `null` se o fundo não é favorito */
    public function one(string $ticker): ?FavoriteSummary
    {
        if (! $this->favorites->exists($ticker)) {
            return null;
        }

        $view = $this->describe->handle($ticker, detailed: true);

        return $view === null ? null : $this->summarize($view);
    }

    private function summarize(FavoriteView $view): FavoriteSummary
    {
        $now = $this->clock->now();
        $today = MarketTime::day($now);
        $price = $view->quote->price ?? $view->close?->close;

        return new FavoriteSummary(
            $view,
            ...$this->changes($view->ticker, $price, $today),
            lastDividend: $this->lastDividend($view->ticker, $today),
            alertsToday: $this->alertsOf($view->ticker, $today),
        );
    }

    /** @return array{0: ?Percentage, 1: ?Percentage} */
    private function changes(string $ticker, ?Money $price, string $today): array
    {
        if ($price === null) {
            return [null, null];
        }

        $from = (new DateTimeImmutable($today))->modify('-'.self::CLOSES_WINDOW_DAYS.' days')->format('Y-m-d');
        $closes = $this->market->closesBefore($ticker, $today, $from);

        return [
            PriceChange::since($closes, $price, $today, self::SHORT_DAYS),
            PriceChange::since($closes, $price, $today, self::LONG_DAYS),
        ];
    }

    private function lastDividend(string $ticker, string $today): ?MonthlyDividend
    {
        $dividends = EstimatedDividends::lastTwelve(
            $this->reports->bookValues($ticker),
            $this->reports->monthlyYields($ticker),
            $today,
        );

        return $dividends === [] ? null : end($dividends);
    }

    /** @return list<Alert> */
    private function alertsOf(string $ticker, string $today): array
    {
        return array_values(array_filter(
            $this->alerts->handle($ticker),
            fn (Alert $alert): bool => MarketTime::day($alert->occurredAt) === $today,
        ));
    }
}

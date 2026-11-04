<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\EstimatedDividends;
use App\Modules\Fiis\Domain\PortfolioLine;
use App\Modules\Fiis\Domain\PortfolioTotals;

/**
 * Monta a carteira com o que já está guardado: não chama a brapi nem carrega
 * histórico de preços (docs/superpowers/specs/2026-11-04-carteira-de-fiis-design.md).
 */
final readonly class ShowPortfolio
{
    public function __construct(
        private HoldingRepository $holdings,
        private FavoriteRepository $favorites,
        private CatalogRepository $catalog,
        private MarketData $market,
        private ReportRepository $reports,
        private Clock $clock,
    ) {}

    public function handle(): PortfolioView
    {
        $day = MarketTime::day($this->clock->now());
        $rows = [];

        foreach ($this->holdings->all() as $ticker => $shares) {
            $quote = $this->favorites->exists($ticker) ? $this->market->latestQuote($ticker) : null;
            // Favorito ainda sem cotação usa o fechamento, em vez de ficar sem preço.
            $close = $quote === null ? $this->market->latestClose($ticker) : null;
            $dividends = EstimatedDividends::lastTwelve($this->reports->bookValues($ticker), $this->reports->monthlyYields($ticker), $day);

            $rows[] = new PortfolioRow(
                new PortfolioLine($ticker, $shares, $quote->price ?? $close?->close, $dividends),
                $this->catalog->nameOf($ticker),
                $quote,
                $close,
            );
        }

        usort($rows, self::byPosition(...));
        $lines = array_map(fn (PortfolioRow $row): PortfolioLine => $row->line, $rows);

        return new PortfolioView($rows, PortfolioTotals::of($lines));
    }

    private static function byPosition(PortfolioRow $a, PortfolioRow $b): int
    {
        $valueA = $a->line->positionValue();
        $valueB = $b->line->positionValue();

        return match (true) {
            $valueA === null && $valueB === null => strcmp($a->line->ticker, $b->line->ticker),
            $valueA === null => 1,
            $valueB === null => -1,
            $valueA->isLessThan($valueB) => 1,
            $valueB->isLessThan($valueA) => -1,
            default => strcmp($a->line->ticker, $b->line->ticker),
        };
    }
}

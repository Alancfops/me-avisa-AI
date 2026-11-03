<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\DividendYield;
use App\Modules\Fiis\Domain\EstimatedDividends;
use App\Modules\Fiis\Domain\MonthlyDividend;
use App\Modules\Fiis\Domain\MonthlyYield;
use App\Modules\Fiis\Domain\PriceHistory;
use App\Modules\Fiis\Domain\PricePoint;
use App\Modules\Fiis\Domain\RuleKind;
use App\Modules\Fiis\Domain\WindowAverage;

final readonly class DescribeFund
{
    public function __construct(
        private FavoriteRepository $favorites,
        private CatalogRepository $catalog,
        private MarketData $market,
        private BuildFundSnapshot $snapshots,
        private ReportRepository $reports,
        private Clock $clock,
    ) {}

    /**
     * @param  bool  $detailed  perfil, médias, histórico e rendimentos: só a página do
     *                          fundo precisa, e a lista não deve carregar um ano de preços por favorito
     * @return FavoriteView|null `null` se o fundo não for um favorito
     */
    public function handle(string $ticker, bool $detailed = false): ?FavoriteView
    {
        $settings = $this->favorites->settings($ticker);

        if ($settings === null) {
            return null;
        }

        $quote = $this->market->latestQuote($ticker);
        $snapshot = $quote === null ? null : $this->snapshots->handle($quote);
        $rules = [];

        if ($snapshot !== null) {
            foreach ($settings->rules() as $kind => $rule) {
                $rules[] = new RuleStatus(RuleKind::from($kind), $rule->evaluate($snapshot));
            }
        }

        $day = MarketTime::day($quote === null ? $this->clock->now() : $quote->quotedAt);
        $yields = $this->reports->monthlyYields($ticker);
        $details = $detailed ? $this->details($ticker, $day, $yields) : [null, new WindowAverage(6, 0, null), new WindowAverage(12, 0, null), [], []];

        return new FavoriteView(
            $ticker,
            $this->catalog->nameOf($ticker),
            $quote,
            $settings,
            $snapshot,
            $rules,
            DividendYield::trailing12Months($yields, MarketTime::day($this->clock->now())),
            ...$details,
        );
    }

    /**
     * @param  list<MonthlyYield>  $yields
     * @return array{FundProfile|null, WindowAverage, WindowAverage, list<PricePoint>, list<MonthlyDividend>}
     */
    private function details(string $ticker, string $day, array $yields): array
    {
        $closes = $this->market->closesBefore($ticker, $day, MarketTime::yearWindowFetchStart($day));
        $bookValues = $this->reports->bookValues($ticker);

        return [
            $this->reports->profile($ticker),
            PriceHistory::average($closes, $day, 6),
            PriceHistory::average($closes, $day, 12),
            PriceHistory::points($closes, $bookValues, $day),
            EstimatedDividends::lastTwelve($bookValues, $yields, $day),
        ];
    }
}

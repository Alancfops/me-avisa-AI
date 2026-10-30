<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\DividendYield;
use App\Modules\Fiis\Domain\RuleKind;

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

    /** @return FavoriteView|null `null` se o fundo não for um favorito */
    public function handle(string $ticker): ?FavoriteView
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

        return new FavoriteView(
            $ticker,
            $this->catalog->nameOf($ticker),
            $quote,
            $settings,
            $snapshot,
            $rules,
            DividendYield::trailing12Months($this->reports->monthlyYields($ticker), MarketTime::day($this->clock->now())),
        );
    }
}

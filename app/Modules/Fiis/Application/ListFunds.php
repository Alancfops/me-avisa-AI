<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\DividendYield;
use App\Modules\Fiis\Domain\FundSnapshot;
use App\Modules\Fiis\Domain\Ratio;

final readonly class ListFunds
{
    public function __construct(
        private CatalogRepository $catalog,
        private MarketData $market,
        private ReportRepository $reports,
        private FavoriteRepository $favorites,
        private Clock $clock,
    ) {}

    /**
     * Fundos do catálogo que negociaram nos últimos 10 dias úteis. Tudo é lido em lote
     * (um conjunto de consultas fixo, não uma por fundo).
     *
     * @return list<FundListItem>
     */
    public function handle(): array
    {
        $closes = $this->market->latestCloses();
        $bookValues = $this->reports->allBookValues();
        $yields = $this->reports->allMonthlyYields();
        $profiles = $this->reports->profiles();
        $kinds = $this->reports->kinds();
        $favorites = array_flip($this->favorites->tickers());
        $today = MarketTime::day($this->clock->now());

        $items = [];

        foreach ($this->catalog->all() as $entry) {
            $close = $closes[$entry->ticker] ?? null;

            if ($close === null) {
                continue;
            }

            $report = FundSnapshot::reportInForce($bookValues[$entry->ticker] ?? [], $close->date);
            $profile = $profiles[$entry->ticker] ?? null;

            $items[] = new FundListItem(
                $entry->ticker,
                $entry->name,
                $kinds[$entry->ticker] ?? null,
                $profile?->segment,
                $close->close,
                $close->date,
                $report === null ? null : Ratio::of($close->close, $report->bookValue),
                DividendYield::trailing12Months($yields[$entry->ticker] ?? [], $today),
                $profile?->netAssets,
                isset($favorites[$entry->ticker]),
            );
        }

        return $items;
    }
}

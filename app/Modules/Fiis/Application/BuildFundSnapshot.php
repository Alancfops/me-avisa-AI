<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\FundSnapshot;

final readonly class BuildFundSnapshot
{
    /** Cobre os 12 meses da mediana, que também contêm os 30 pregões da média. */
    private const HISTORY_YEARS = 1;

    public function __construct(
        private MarketData $market,
        private ReportRepository $reports,
    ) {}

    public function handle(Quote $quote): FundSnapshot
    {
        $day = MarketTime::day($quote->quotedAt);
        $fromDay = ((int) substr($day, 0, 4) - self::HISTORY_YEARS).substr($day, 4);

        return FundSnapshot::fromHistory(
            $quote->ticker,
            $quote->price,
            $quote->changePercent,
            $day,
            $this->market->closesBefore($quote->ticker, $day, $fromDay),
            $this->reports->bookValues($quote->ticker),
        );
    }
}

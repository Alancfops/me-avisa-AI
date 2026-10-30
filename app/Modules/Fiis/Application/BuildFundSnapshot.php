<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\FundSnapshot;

final readonly class BuildFundSnapshot
{
    public function __construct(
        private MarketData $market,
        private ReportRepository $reports,
    ) {}

    public function handle(Quote $quote): FundSnapshot
    {
        $day = MarketTime::day($quote->quotedAt);
        $fromDay = MarketTime::yearBefore($day);

        // Um ano cobre os 12 meses da mediana e os 30 pregões da média.
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

<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\FundSnapshot;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Percentage;

final readonly class BuildFundSnapshot
{
    public function __construct(
        private MarketData $market,
        private ReportRepository $reports,
    ) {}

    public function handle(Quote $quote): FundSnapshot
    {
        return $this->at($quote->ticker, $quote->price, $quote->changePercent, MarketTime::day($quote->quotedAt));
    }

    /** Retrato de um fundo sem cotação, como estava no pregão do último fechamento. */
    public function atClose(string $ticker, DailyClose $close): FundSnapshot
    {
        return $this->at($ticker, $close->close, null, $close->date);
    }

    private function at(string $ticker, Money $price, ?Percentage $changePercent, string $day): FundSnapshot
    {
        // Um ano cobre os 12 meses da mediana e os 30 pregões da média.
        return FundSnapshot::fromHistory(
            $ticker,
            $price,
            $changePercent,
            $day,
            $this->market->closesBefore($ticker, $day, MarketTime::yearBefore($day)),
            $this->reports->bookValues($ticker),
        );
    }
}

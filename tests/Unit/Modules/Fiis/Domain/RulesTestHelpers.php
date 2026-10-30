<?php

use App\Modules\Fiis\Domain\FundSnapshot;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Percentage;
use App\Modules\Fiis\Domain\Ratio;

function fund(
    string $price = '10.00',
    ?string $change = null,
    ?string $average = null,
    ?string $pvp = null,
    ?string $median = null,
    int $days = 0,
): FundSnapshot {
    return new FundSnapshot(
        'MXRF11',
        Money::fromString($price),
        changePercent: $change === null ? null : Percentage::fromString($change),
        average30d: $average === null ? null : Money::fromString($average),
        priceToBook: $pvp === null ? null : Ratio::fromString($pvp),
        medianPriceToBook: $median === null ? null : Ratio::fromString($median),
        priceToBookDays: $days,
    );
}

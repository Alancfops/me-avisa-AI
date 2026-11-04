<?php

use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\MonthlyDividend;
use App\Modules\Fiis\Domain\PortfolioLine;
use App\Modules\Fiis\Domain\PortfolioTotals;

/** @param list<string> $perShare */
function portfolioLine(string $ticker, int $shares, ?string $price, array $perShare): PortfolioLine
{
    $dividends = [];
    foreach ($perShare as $i => $value) {
        $dividends[] = new MonthlyDividend(sprintf('2026-%02d', $i + 1), Money::fromString($value));
    }

    return new PortfolioLine($ticker, $shares, $price === null ? null : Money::fromString($price), $dividends);
}

it('multiplies price and dividends by the shares', function () {
    $line = portfolioLine('KNRI11', 100, '160.00', ['0.90', '1.10']);

    expect($line->positionValue()?->toDecimal())->toBe('16000')
        ->and($line->lastIncome()?->toDecimal())->toBe('110')
        ->and($line->averageIncome()?->toDecimal())->toBe('100')
        ->and($line->dividendMonths())->toBe(2);
});

it('has no value without price and no income without dividends', function () {
    $line = portfolioLine('XPTO11', 10, null, []);

    expect($line->positionValue())->toBeNull()
        ->and($line->lastIncome())->toBeNull()
        ->and($line->averageIncome())->toBeNull()
        ->and($line->dividendMonths())->toBe(0);
});

it('sums only the lines with data and counts the rest', function () {
    $totals = PortfolioTotals::of([
        portfolioLine('AAAA11', 10, '10', ['0.10']),
        portfolioLine('BBBB11', 5, null, ['0.20']),
        portfolioLine('CCCC11', 1, '20', []),
    ]);

    expect($totals->value?->toDecimal())->toBe('120')
        ->and($totals->lastIncome?->toDecimal())->toBe('2')
        ->and($totals->averageIncome?->toDecimal())->toBe('2')
        ->and($totals->withoutPrice)->toBe(1)
        ->and($totals->withoutDividends)->toBe(1);
});

it('has null totals for an empty portfolio', function () {
    $totals = PortfolioTotals::of([]);

    expect($totals->value)->toBeNull()
        ->and($totals->lastIncome)->toBeNull()
        ->and($totals->averageIncome)->toBeNull()
        ->and($totals->withoutPrice)->toBe(0)
        ->and($totals->withoutDividends)->toBe(0);
});

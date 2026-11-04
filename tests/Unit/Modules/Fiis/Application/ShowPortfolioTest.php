<?php

use App\Modules\Fiis\Application\PortfolioRow;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Application\ShowPortfolio;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\Money;
use Tests\Support\Fiis\FixedClock;
use Tests\Support\Fiis\InMemoryCatalog;
use Tests\Support\Fiis\InMemoryFavorites;
use Tests\Support\Fiis\InMemoryHoldings;
use Tests\Support\Fiis\InMemoryMarketData;
use Tests\Support\Fiis\InMemoryReports;

function portfolioOf(array $holdings, array $favorites, InMemoryMarketData $market, array $names = []): ShowPortfolio
{
    return new ShowPortfolio(
        new InMemoryHoldings($holdings),
        new InMemoryFavorites($favorites),
        new InMemoryCatalog($names),
        $market,
        new InMemoryReports,
        new FixedClock,
    );
}

it('prices favorites by the quote and the others by the close', function () {
    $market = new InMemoryMarketData;
    $market->saveQuote(new Quote('KNRI11', Money::fromString('160'), null, null, new DateTimeImmutable('2026-10-30 14:00:00-03:00')));
    $market->saveCloses('KNRI11', [new DailyClose('2026-10-29', Money::fromString('150'))]);
    $market->saveCloses('MXRF11', [new DailyClose('2026-10-29', Money::fromString('9.50'))]);

    $rows = portfolioOf(['KNRI11' => 1, 'MXRF11' => 1], ['KNRI11'], $market)->handle()->rows;
    $byTicker = array_column(array_map(fn (PortfolioRow $row) => [$row->line->ticker, $row], $rows), 1, 0);

    expect($byTicker['KNRI11']->quote?->price->toDecimal())->toBe('160')
        ->and($byTicker['KNRI11']->close)->toBeNull()
        ->and($byTicker['KNRI11']->line->price?->toDecimal())->toBe('160')
        ->and($byTicker['MXRF11']->quote)->toBeNull()
        ->and($byTicker['MXRF11']->close?->date)->toBe('2026-10-29')
        ->and($byTicker['MXRF11']->line->price?->toDecimal())->toBe('9.5');
});

it('orders by position value, with unpriced funds last by ticker', function () {
    $market = new InMemoryMarketData;
    $market->saveCloses('AAAA11', [new DailyClose('2026-10-29', Money::fromString('10'))]);
    $market->saveCloses('BBBB11', [new DailyClose('2026-10-29', Money::fromString('100'))]);

    $view = portfolioOf(['AAAA11' => 5, 'BBBB11' => 1, 'ZZZZ11' => 1, 'CCCC11' => 1], [], $market)->handle();

    expect(array_map(fn (PortfolioRow $row) => $row->line->ticker, $view->rows))
        ->toBe(['BBBB11', 'AAAA11', 'CCCC11', 'ZZZZ11'])
        ->and($view->totals->value?->toDecimal())->toBe('150')
        ->and($view->totals->withoutPrice)->toBe(2);
});

it('keeps a fund that left the catalog, without a name', function () {
    $rows = portfolioOf(['KNRI11' => 1, 'OLDX11' => 1], [], new InMemoryMarketData, ['KNRI11' => 'Kinea'])->handle()->rows;

    expect(array_map(fn (PortfolioRow $row) => [$row->line->ticker, $row->name], $rows))
        ->toBe([['KNRI11', 'Kinea'], ['OLDX11', null]]);
});

it('shows an empty portfolio with null totals', function () {
    $view = portfolioOf([], [], new InMemoryMarketData)->handle();

    expect($view->rows)->toBe([])
        ->and($view->totals->value)->toBeNull()
        ->and($view->totals->lastIncome)->toBeNull();
});

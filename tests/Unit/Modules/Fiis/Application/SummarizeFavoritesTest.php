<?php

use App\Modules\Fiis\Application\Alert;
use App\Modules\Fiis\Application\BuildFundSnapshot;
use App\Modules\Fiis\Application\CatalogRepository;
use App\Modules\Fiis\Application\DescribeFund;
use App\Modules\Fiis\Application\ListAlerts;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Application\SummarizeFavorites;
use App\Modules\Fiis\Domain\BookValueReport;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\MonthlyYield;
use App\Modules\Fiis\Domain\RuleKind;
use Tests\Support\Fiis\FixedClock;
use Tests\Support\Fiis\InMemoryAlerts;
use Tests\Support\Fiis\InMemoryFavorites;
use Tests\Support\Fiis\InMemoryMarketData;
use Tests\Support\Fiis\InMemoryReports;

function summarizer(InMemoryFavorites $favorites, InMemoryMarketData $market, InMemoryReports $reports, InMemoryAlerts $alerts): SummarizeFavorites
{
    $clock = new FixedClock; // sexta, 2026-10-30
    $catalog = Mockery::mock(CatalogRepository::class);
    $catalog->shouldReceive('exists')->andReturn(true);
    $catalog->shouldReceive('nameOf')->andReturn('Maxi Renda');
    $describe = new DescribeFund($favorites, $catalog, $market, new BuildFundSnapshot($market, $reports), $reports, $clock);

    return new SummarizeFavorites($favorites, $describe, $market, $reports, new ListAlerts($alerts), $clock);
}

function alertAt(string $ticker, string $moment): Alert
{
    $at = new DateTimeImmutable($moment);

    return new Alert($ticker, RuleKind::PriceTarget, Money::fromString('11'), 'alvo', 'motivo', $at, $at);
}

it('summarizes a favorite with quote, changes, last dividend and alerts of today', function () {
    $market = new InMemoryMarketData;
    $market->saveQuote(new Quote('MXRF11', Money::fromString('11.00'), null, null, new DateTimeImmutable('2026-10-30T14:00:00-03:00')));
    $market->saveCloses('MXRF11', [
        new DailyClose('2026-09-30', Money::fromString('20.00')),
        new DailyClose('2026-10-23', Money::fromString('10.00')),
    ]);
    $reports = new InMemoryReports(
        ['MXRF11' => [new BookValueReport('2026-09', '2026-10-10', Money::fromString('100.00'))]],
        ['MXRF11' => [new MonthlyYield('2026-09', '2026-10-10', '0.005')]],
    );
    $alerts = new InMemoryAlerts;
    $alerts->alerts = [alertAt('MXRF11', '2026-10-29T15:00:00-03:00'), alertAt('MXRF11', '2026-10-30T10:00:00-03:00'), alertAt('KNRI11', '2026-10-30T10:00:00-03:00')];

    $summary = summarizer(new InMemoryFavorites(['MXRF11']), $market, $reports, $alerts)->all()[0];

    expect($summary->view->ticker)->toBe('MXRF11')
        ->and($summary->change7d->toDecimal())->toBe('10')
        ->and($summary->change30d->toDecimal())->toBe('-45')
        ->and($summary->lastDividend->month)->toBe('2026-09')
        ->and((string) $summary->lastDividend->perShare)->toBe('R$ 0,50')
        ->and($summary->alertsToday)->toHaveCount(1)
        ->and($summary->alertsToday[0]->ticker)->toBe('MXRF11');
});

it('leaves changes and dividend empty for a favorite without quote or closes', function () {
    $summary = summarizer(new InMemoryFavorites(['MXRF11']), new InMemoryMarketData, new InMemoryReports, new InMemoryAlerts)->all()[0];

    expect($summary->change7d)->toBeNull()
        ->and($summary->change30d)->toBeNull()
        ->and($summary->lastDividend)->toBeNull()
        ->and($summary->alertsToday)->toBe([]);
});

it('returns null in one() for a fund that is not a favorite', function () {
    $summarize = summarizer(new InMemoryFavorites(['MXRF11']), new InMemoryMarketData, new InMemoryReports, new InMemoryAlerts);

    expect($summarize->one('KNRI11'))->toBeNull()
        ->and($summarize->one('MXRF11'))->not->toBeNull();
});

it('filters latest alerts by ticker', function () {
    $alerts = new InMemoryAlerts;
    $alerts->alerts = [alertAt('MXRF11', '2026-10-30T10:00:00-03:00'), alertAt('KNRI11', '2026-10-30T11:00:00-03:00')];

    expect($alerts->latest(10, 'MXRF11'))->toHaveCount(1)
        ->and($alerts->latest(10))->toHaveCount(2);
});

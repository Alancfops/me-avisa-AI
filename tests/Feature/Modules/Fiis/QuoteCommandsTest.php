<?php

use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundModel;
use App\Modules\Fiis\Infrastructure\Persistence\QuoteModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

function brapiBody(string $name): string
{
    return (string) file_get_contents(__DIR__."/../../../Fixtures/brapi/{$name}.json");
}

beforeEach(fn () => Carbon::setTestNow('2026-10-30 15:00:00-03:00'));
afterEach(fn () => Carbon::setTestNow());

it('refreshes quotes by command, one request per favorite', function () {
    FavoriteModel::create(['ticker' => 'KNRI11']);
    Http::fake(['*' => Http::response(brapiBody('quote-knri11'))]);

    $this->artisan('fiis:refresh-quotes')->assertSuccessful();

    expect(QuoteModel::find('KNRI11')->price)->toEqual('149.910000');
    Http::assertSentCount(1);
});

it('keeps the good quote when the next one breaks', function () {
    FavoriteModel::create(['ticker' => 'KNRI11']);
    QuoteModel::create(['ticker' => 'KNRI11', 'price' => '150', 'quoted_at' => '2026-10-30T16:00:00Z']);
    Http::fake(['*' => Http::response(['results' => [['symbol' => 'KNRI11', 'data' => ['regularMarketPrice' => 0, 'regularMarketTime' => '2026-10-30T17:57:30.000Z']]]])]);

    $this->artisan('fiis:refresh-quotes')->assertSuccessful();

    expect(QuoteModel::find('KNRI11')->price)->toEqual('150.000000');
});

it('does not replace a quote with an older one', function () {
    FavoriteModel::create(['ticker' => 'KNRI11']);
    QuoteModel::create(['ticker' => 'KNRI11', 'price' => '150', 'quoted_at' => '2026-10-30T18:30:00Z']);
    Http::fake(['*' => Http::response(brapiBody('quote-knri11'))]);

    $this->artisan('fiis:refresh-quotes')->assertSuccessful();

    expect(QuoteModel::find('KNRI11')->price)->toEqual('150.000000');
});

it('syncs the catalog without erasing what it already had', function () {
    FundModel::create(['ticker' => 'OLDA11', 'name' => 'Antigo']);
    Http::fake([
        '*page=1*' => Http::response(brapiBody('tickers-page1')),
        '*page=2*' => Http::response(brapiBody('tickers-page2')),
    ]);

    $this->artisan('fiis:sync-catalog')->assertSuccessful();

    expect(FundModel::orderBy('ticker')->pluck('ticker')->all())->toBe(['KNRI11', 'MXRF11', 'OLDA11', 'VISC11']);
});

it('keeps the catalog and fails loudly when the source breaks', function () {
    FundModel::create(['ticker' => 'OLDA11', 'name' => 'Antigo']);
    Http::fake(['*' => Http::response('', 500)]);

    $this->artisan('fiis:sync-catalog')->assertFailed();

    expect(FundModel::count())->toBe(1);
});

<?php

use App\Modules\Fiis\Infrastructure\Persistence\DailyPriceModel;
use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use App\Modules\Fiis\Infrastructure\Persistence\SourceEventModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

function usageResponse(): array
{
    return ['usage' => ['planName' => 'free', 'planLimit' => 15000, 'currentUsage' => 8, 'remainingUsage' => 14992,
        'usageWindow' => ['type' => 'rolling-30d', 'start' => '2026-09-30T00:00:00.000Z', 'end' => '2026-10-30T17:58:28.443Z']]];
}

beforeEach(fn () => Carbon::setTestNow('2026-10-30 15:00:00-03:00'));
afterEach(fn () => Carbon::setTestNow());

it('shows each source, even before anything ran', function () {
    Http::fake(['*/user/usage' => Http::response(usageResponse())]);

    $this->get('/saude')->assertInertia(fn ($page) => $page
        ->component('health')
        ->where('sources.0.source', 'brapi')
        ->where('sources.0.lastSuccessAt', null)
        ->where('sources.0.errors', [])
        ->where('sources.1.source', 'cvm'));
});

it('registers a source failure and shows it, in plain words', function () {
    FavoriteModel::create(['ticker' => 'KNRI11']);
    Http::fake([
        '*/stocks/quote*' => Http::response('', 401),
        '*/user/usage' => Http::response(usageResponse()),
    ]);

    $this->artisan('fiis:refresh-quotes')->assertSuccessful();

    $this->get('/saude')->assertInertia(fn ($page) => $page
        ->where('sources.0.lastSuccessAt', null)
        ->where('sources.0.errors.0.message', 'KNRI11: A brapi recusou o token (401). Confira o BRAPI_TOKEN no .env.')
        ->where('sources.0.errors.0.occurredAt', '2026-10-30T15:00:00-03:00'));
});

it('shows the last success next to older errors', function () {
    FavoriteModel::create(['ticker' => 'KNRI11']);
    Http::fake([
        '*/stocks/quote*' => Http::sequence()
            ->push('', 500)
            ->push(['results' => [['symbol' => 'KNRI11', 'data' => ['regularMarketPrice' => 10, 'regularMarketTime' => '2026-10-30T18:00:00.000Z']]]]),
        '*/user/usage' => Http::response(usageResponse()),
    ]);

    $this->artisan('fiis:refresh-quotes');
    Carbon::setTestNow('2026-10-30 15:30:00-03:00');
    $this->artisan('fiis:refresh-quotes');

    $this->get('/saude')->assertInertia(fn ($page) => $page
        ->where('sources.0.lastSuccessAt', '2026-10-30T15:30:00-03:00')
        ->has('sources.0.errors', 1));
});

it('registers a failed CVM import', function () {
    Http::fake([
        '*.zip' => Http::response('', 500),
        '*/user/usage' => Http::response(usageResponse()),
    ]);

    $this->artisan('fiis:import-reports')->assertFailed();

    $this->get('/saude')->assertInertia(fn ($page) => $page
        ->where('sources.1.source', 'cvm')
        ->has('sources.1.errors', 2));
});

it('registers a failed catalog sync', function () {
    Http::fake([
        '*/tickers*' => Http::response('', 500),
        '*/user/usage' => Http::response(usageResponse()),
    ]);

    $this->artisan('fiis:sync-catalog')->assertFailed();

    $this->get('/saude')->assertInertia(fn ($page) => $page->has('sources.0.errors', 1));
});

it('shows the quota usage from brapi, without spending a request on every visit', function () {
    Http::fake(['*/user/usage' => Http::response(usageResponse())]);

    $this->get('/saude');
    $this->get('/saude')->assertInertia(fn ($page) => $page
        ->where('usage', ['plan' => 'free', 'limit' => 15000, 'used' => 8, 'remaining' => 14992]));

    Http::assertSentCount(1);
});

it('says the usage is unavailable when brapi does not answer', function (mixed $body, int $status) {
    Http::fake(['*/user/usage' => Http::response($body, $status)]);

    $this->get('/saude')->assertOk()->assertInertia(fn ($page) => $page->where('usage', null));
})->with([
    'erro' => ['', 500],
    'token recusado' => ['', 401],
    'quebrado' => [['usage' => ['planLimit' => 'muito']], 200],
]);

it('never exposes the token on the page', function () {
    Http::fake(['*/user/usage' => Http::response('test-token', 500)]);

    $this->get('/saude')->assertDontSee('test-token');
});

it('keeps only recent events', function () {
    SourceEventModel::create(['source' => 'brapi', 'kind' => 'failure', 'message' => 'velho', 'occurred_at' => '2026-08-01T10:00:00-03:00']);
    FavoriteModel::create(['ticker' => 'KNRI11']);
    Http::fake(['*/stocks/quote*' => Http::response('', 500)]);

    $this->artisan('fiis:refresh-quotes');

    expect(SourceEventModel::where('message', 'velho')->exists())->toBeFalse();
});

it('records daily closes for every favorite, and keeps going when one fails', function () {
    FavoriteModel::create(['ticker' => 'KNRI11']);
    FavoriteModel::create(['ticker' => 'MXRF11']);
    Carbon::setTestNow('2026-10-30 19:00:00-03:00');
    Http::fake([
        '*symbols=KNRI11*' => Http::response('', 500),
        '*symbols=MXRF11*' => Http::response(['results' => [['symbol' => 'MXRF11', 'data' => ['historicalDataPrice' => [
            ['date' => 1793329200, 'close' => 8.91],
        ]]]]]),
    ]);

    $this->artisan('fiis:record-closes')->assertSuccessful();

    expect(DailyPriceModel::where('ticker', 'MXRF11')->count())->toBe(1);
});

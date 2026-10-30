<?php

use App\Modules\Fiis\Application\Quotes;
use App\Modules\Fiis\Application\RefreshQuotes;
use App\Modules\Fiis\Application\SourceUnavailable;
use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function brapiFixture(string $name): string
{
    return (string) file_get_contents(__DIR__."/../../../../Fixtures/brapi/{$name}.json");
}

function quoteBody(array $data): array
{
    return ['results' => [['symbol' => 'KNRI11', 'data' => $data]]];
}

beforeEach(fn () => Carbon::setTestNow('2026-10-30 15:00:00-03:00'));
afterEach(fn () => Carbon::setTestNow());

it('parses a recorded quote and sends the token by header', function () {
    Http::fake(['*' => Http::response(brapiFixture('quote-knri11'))]);

    $quote = app(Quotes::class)->latest('KNRI11');

    expect($quote->ticker)->toBe('KNRI11')
        ->and((string) $quote->price)->toBe('R$ 149,91')
        ->and($quote->changePercent->toDecimal())->toBe('0.21')
        ->and($quote->volume)->toBe(27898)
        ->and($quote->quotedAt->getTimestamp())->toBe(strtotime('2026-10-30T17:57:30Z'));

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/v2/stocks/quote?symbols=KNRI11')
        && $request->hasHeader('Authorization', 'Bearer test-token')
        && ! str_contains($request->url(), 'test-token'));
});

it('accepts a missing change or volume as null, never zero', function () {
    Http::fake(['*' => Http::response(quoteBody([
        'regularMarketPrice' => 10.5,
        'regularMarketTime' => '2026-10-30T17:57:30.000Z',
        'regularMarketChangePercent' => null,
    ]))]);

    $quote = app(Quotes::class)->latest('KNRI11');

    expect($quote->changePercent)->toBeNull()->and($quote->volume)->toBeNull();
});

it('rejects a broken quote', function (mixed $body) {
    Http::fake(['*' => Http::response($body)]);

    expect(fn () => app(Quotes::class)->latest('KNRI11'))->toThrow(SourceUnavailable::class);
})->with([
    'não é JSON' => ['<html>erro</html>'],
    'sem resultados' => [['results' => []]],
    'sem data' => [['results' => [['symbol' => 'KNRI11']]]],
    'ativo trocado' => [['results' => [['symbol' => 'MXRF11', 'data' => ['regularMarketPrice' => 9, 'regularMarketTime' => '2026-10-30T17:57:30.000Z']]]]],
    'preço zero' => [quoteBody(['regularMarketPrice' => 0, 'regularMarketTime' => '2026-10-30T17:57:30.000Z'])],
    'preço negativo' => [quoteBody(['regularMarketPrice' => -1.5, 'regularMarketTime' => '2026-10-30T17:57:30.000Z'])],
    'preço null' => [quoteBody(['regularMarketPrice' => null, 'regularMarketTime' => '2026-10-30T17:57:30.000Z'])],
    'preço em texto' => [quoteBody(['regularMarketPrice' => '10', 'regularMarketTime' => '2026-10-30T17:57:30.000Z'])],
    'sem horário' => [quoteBody(['regularMarketPrice' => 10])],
    'horário inválido' => [quoteBody(['regularMarketPrice' => 10, 'regularMarketTime' => 'ontem'])],
    'horário no futuro' => [quoteBody(['regularMarketPrice' => 10, 'regularMarketTime' => '2026-10-31T17:57:30.000Z'])],
    'variação em texto' => [quoteBody(['regularMarketPrice' => 10, 'regularMarketChangePercent' => 'n/a', 'regularMarketTime' => '2026-10-30T17:57:30.000Z'])],
]);

it('explains each http failure', function (int $status, string $message) {
    Http::fake(['*' => Http::response(['error' => true], $status)]);

    expect(fn () => app(Quotes::class)->latest('KNRI11'))->toThrow(SourceUnavailable::class, $message);
})->with([
    [401, 'token'],
    [403, 'plano'],
    [404, 'não encontrou'],
    [429, 'Limite'],
    [500, 'indisponível'],
]);

it('treats a connection failure as an unavailable source', function () {
    Http::fake(['*' => Http::failedConnection()]);

    expect(fn () => app(Quotes::class)->latest('KNRI11'))->toThrow(SourceUnavailable::class, 'conectar');
});

it('respects Retry-After without spending requests', function (string $header, int $seconds) {
    Http::fake(['*' => Http::sequence()
        ->push('', 429, ['Retry-After' => $header])
        ->push(brapiFixture('quote-knri11'))]);
    $quotes = app(Quotes::class);

    expect(fn () => $quotes->latest('KNRI11'))->toThrow(SourceUnavailable::class);
    expect(fn () => $quotes->latest('MXRF11'))->toThrow(SourceUnavailable::class, 'Limite');
    Http::assertSentCount(1);

    Carbon::setTestNow(now()->addSeconds($seconds));

    expect($quotes->latest('KNRI11')->ticker)->toBe('KNRI11');
})->with([
    'em segundos' => ['120', 120],
    'como data' => ['Fri, 30 Oct 2026 18:02:00 GMT', 120],
]);

it('keeps asking when a 429 has no usable Retry-After', function () {
    Http::fake(['*' => Http::response('', 429, ['Retry-After' => 'depois'])]);
    $quotes = app(Quotes::class);

    expect(fn () => $quotes->latest('KNRI11'))->toThrow(SourceUnavailable::class);
    expect(fn () => $quotes->latest('MXRF11'))->toThrow(SourceUnavailable::class);
    Http::assertSentCount(2);
    expect(Cache::get('brapi.blocked_until'))->toBeNull();
});

it('reads the history using close, newest bar first, skipping invalid bars', function () {
    Http::fake(['*' => Http::response(brapiFixture('historical-knri11'))]);

    $closes = app(Quotes::class)->history('KNRI11');

    expect(array_map(fn ($c) => [$c->date, $c->close->toDecimal()], $closes))->toBe([
        ['2026-08-04', '152.09'],
        ['2026-10-29', '149.6'],
        ['2026-10-30', '149.91'],
    ]);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/v2/stocks/historical?symbols=KNRI11&range=3mo&interval=1d'));
});

it('never logs the token', function (Closure $fake) {
    $fake();
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message.json_encode($event->context);
    });
    FavoriteModel::create(['ticker' => 'KNRI11']);

    app(RefreshQuotes::class)->handle();

    expect($logged)->not->toBeEmpty()
        ->and(implode("\n", $logged))->not->toContain('test-token');
})->with([
    '401 que ecoa o token' => [fn () => Http::fake(['*' => Http::response('invalid token test-token', 401)])],
    'corpo quebrado que ecoa o token' => [fn () => Http::fake(['*' => Http::response('Bearer test-token')])],
    'falha de conexão' => [fn () => Http::fake(['*' => Http::failedConnection('Bearer test-token')])],
]);

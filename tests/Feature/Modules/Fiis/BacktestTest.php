<?php

use App\Modules\Fiis\Infrastructure\Persistence\AlertModel;
use App\Modules\Fiis\Infrastructure\Persistence\DailyPriceModel;
use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundReportModel;
use App\Modules\Fiis\Infrastructure\Persistence\QuoteModel;
use App\Modules\Fiis\Infrastructure\Persistence\RuleStateModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Carbon::setTestNow('2026-10-30 15:00:00-03:00'));
afterEach(fn () => Carbon::setTestNow());

function storeCloses(string $ticker, array $closes): void
{
    foreach ($closes as $day => $close) {
        DailyPriceModel::create(['ticker' => $ticker, 'day' => $day, 'close' => $close]);
    }
}

function favoriteWithTarget(string $ticker, string $target, bool $sharpDrop = false): void
{
    FavoriteModel::create(['ticker' => $ticker, 'mode' => 'target', 'target' => $target, 'sharp_drop' => $sharpDrop]);
}

it('counts what would have fired, using the same antispam', function () {
    favoriteWithTarget('MXRF11', '9.50');
    storeCloses('MXRF11', [
        '2026-10-01' => '10.00',
        '2026-10-02' => '9.40', // cruza
        '2026-10-05' => '9.40', // continua: não repete
        '2026-10-06' => '9.21', // 2% abaixo: repete
        '2026-10-07' => '10.00', // deixa de valer
        '2026-10-08' => '9.21', // volta depois de 24 h do último
    ]);

    $this->artisan('fiis:backtest', ['ticker' => 'mxrf11'])
        ->expectsOutputToContain('Avisos que teriam saído: 3')
        ->expectsOutputToContain('2026-10-02')
        ->expectsOutputToContain('2026-10-06')
        ->expectsOutputToContain('2026-10-08')
        ->doesntExpectOutputToContain('2026-10-05')
        ->assertSuccessful();
});

it('warns that it only counts', function () {
    favoriteWithTarget('MXRF11', '9.50');
    storeCloses('MXRF11', ['2026-10-01' => '10.00', '2026-10-02' => '9.40']);

    $this->artisan('fiis:backtest', ['ticker' => 'MXRF11'])
        ->expectsOutputToContain('Só contagem: não diz se a compra daria lucro. Nada foi gravado nem enviado.')
        ->assertSuccessful();
});

it('uses the same sharp drop rule, from close to close, strict at -3%', function () {
    favoriteWithTarget('MXRF11', '1.00', sharpDrop: true);
    storeCloses('MXRF11', [
        '2026-10-01' => '10.00',
        '2026-10-02' => '9.70', // exatamente −3%: não dispara
        '2026-10-05' => '9.40', // −3,09%
    ]);

    $this->artisan('fiis:backtest', ['ticker' => 'MXRF11'])
        ->expectsOutputToContain('Avisos que teriam saído: 1')
        ->expectsOutputToContain('2026-10-05')
        ->assertSuccessful();
});

it('applies the automatic rule with no look-ahead, and defaults to it outside favorites', function () {
    $closes = [];
    $day = Carbon::parse('2026-07-01');
    foreach (range(1, 35) as $i) {
        $closes[$day->format('Y-m-d')] = '10.00';
        $day = $day->addDay();
    }
    storeCloses('KNRI11', $closes);
    storeCloses('KNRI11', [$day->format('Y-m-d') => '9.00']);
    // Só entregue depois da queda: naquele dia ainda não era público.
    FundReportModel::create(['cnpj' => '1', 'ticker' => 'KNRI11', 'month' => '2026-06', 'version' => 1, 'delivered_on' => '2026-10-01', 'book_value' => '20']);

    $this->artisan('fiis:backtest', ['ticker' => 'KNRI11'])
        ->doesntExpectOutputToContain('Regra automática')
        ->expectsOutputToContain('VP indisponível')
        ->assertSuccessful();

    FundReportModel::query()->update(['delivered_on' => '2026-07-01']);

    $this->artisan('fiis:backtest', ['ticker' => 'KNRI11'])
        ->expectsOutputToContain('Regra automática: 1')
        ->assertSuccessful();
});

it('writes nothing', function () {
    favoriteWithTarget('MXRF11', '9.50');
    storeCloses('MXRF11', ['2026-10-01' => '10.00', '2026-10-02' => '9.40']);
    QuoteModel::create(['ticker' => 'MXRF11', 'price' => '9', 'quoted_at' => '2026-10-29T14:00:00-03:00']);
    $before = DailyPriceModel::count();

    DB::enableQueryLog();
    $this->artisan('fiis:backtest', ['ticker' => 'MXRF11'])->assertSuccessful();
    $writes = collect(DB::getQueryLog())->pluck('query')
        ->filter(fn (string $query) => preg_match('/^\s*(insert|update|delete)/i', $query) === 1);

    expect($writes)->toBeEmpty()
        ->and(AlertModel::count())->toBe(0)
        ->and(RuleStateModel::count())->toBe(0)
        ->and(DailyPriceModel::count())->toBe($before)
        ->and(FavoriteModel::first()->last_evaluated_quote_at)->toBeNull();
});

it('asks the source only when nothing is stored, without saving, and skips the open bar', function () {
    Http::fake(['*/stocks/historical*' => Http::response(['results' => [['symbol' => 'VISC11', 'data' => ['historicalDataPrice' => [
        ['date' => strtotime('2026-10-28 00:00:00 -03:00'), 'close' => 10.00],
        ['date' => strtotime('2026-10-29 00:00:00 -03:00'), 'close' => 9.00],
        ['date' => strtotime('2026-10-30 00:00:00 -03:00'), 'close' => 1.00],
    ]]]]])]);

    $this->artisan('fiis:backtest', ['ticker' => 'VISC11'])
        ->expectsOutputToContain('Queda forte: 1')
        ->assertSuccessful();

    expect(DailyPriceModel::count())->toBe(0);
    Http::assertSentCount(1);
});

it('does not spend a request when the history is stored', function () {
    Http::fake();
    favoriteWithTarget('MXRF11', '9.50');
    storeCloses('MXRF11', ['2026-10-01' => '10.00', '2026-10-02' => '9.40']);

    $this->artisan('fiis:backtest', ['ticker' => 'MXRF11'])->assertSuccessful();

    Http::assertNothingSent();
});

it('fails with a clear message when there is no history', function () {
    Http::fake(['*' => Http::response('', 404)]);

    $this->artisan('fiis:backtest', ['ticker' => 'ABCD11'])
        ->expectsOutputToContain('A brapi não encontrou este ativo')
        ->assertFailed();
});

<?php

use App\Modules\Fiis\Infrastructure\Persistence\DailyPriceModel;
use App\Modules\Fiis\Infrastructure\Persistence\ExchangeImportModel;
use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundReportModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// Sexta-feira. Os 10 dias úteis que contam vão de 2026-10-19 a 2026-10-30.
beforeEach(function () {
    Carbon::setTestNow('2026-10-30 15:00:00-03:00');
    Http::fake();
});
afterEach(fn () => Carbon::setTestNow());

function listedFund(string $ticker, ?string $closeDay = '2026-10-30', string $close = '9.00', string $cnpj = '11111111000111'): void
{
    FundModel::create(['ticker' => $ticker, 'name' => "Fundo {$ticker}"]);

    if ($closeDay !== null) {
        DailyPriceModel::create(['ticker' => $ticker, 'day' => $closeDay, 'close' => $close]);
    }
}

function listedReport(string $ticker, string $cnpj, array $attributes = []): void
{
    FundReportModel::create([
        'cnpj' => $cnpj, 'ticker' => $ticker, 'month' => '2026-09', 'version' => 1,
        'delivered_on' => '2026-10-10', 'book_value' => '10.00', 'segment' => 'Lajes Corporativas', 'net_assets' => '1500000.50',
        'total_invested' => '100', 'real_estate' => '90', 'receivables' => '0', 'fii_shares' => '0',
        ...$attributes,
    ]);
}

it('lists every fund with P/VP, 12 month yield, kind, segment and net assets', function () {
    listedFund('AAAA11', close: '9.00');
    foreach (range(1, 12) as $n) {
        listedReport('AAAA11', '11111111000111', ['month' => sprintf('2025-%02d', $n), 'delivered_on' => sprintf('2025-%02d-20', min($n + 1, 12)), 'dividend_yield' => '0.005', 'book_value' => '10.00']);
    }
    listedFund('BBBB11', close: '20.00');
    FavoriteModel::create(['ticker' => 'BBBB11']);
    ExchangeImportModel::create(['day' => '2026-10-30']);

    $this->get('/fundos')->assertInertia(fn ($page) => $page
        ->component('funds/index')
        ->where('closesOn', '2026-10-30')
        ->has('funds', 2)
        ->where('funds.0', [
            'ticker' => 'AAAA11', 'name' => 'Fundo AAAA11', 'kind' => 'tijolo', 'segment' => 'Lajes Corporativas',
            'close' => '9', 'closeDay' => '2026-10-30', 'priceToBook' => '0.9', 'dividendYield12m' => '0.06',
            'netAssets' => '1500000.50', 'favorite' => false,
        ])
        ->where('funds.1.ticker', 'BBBB11')
        ->where('funds.1.favorite', true)
        ->where('funds.1.priceToBook', null)
        ->where('funds.1.kind', null));
});

it('keeps funds without a report, with null fields', function () {
    listedFund('AAAA11');

    $this->get('/fundos')->assertInertia(fn ($page) => $page
        ->where('funds.0', [
            'ticker' => 'AAAA11', 'name' => 'Fundo AAAA11', 'kind' => null, 'segment' => null,
            'close' => '9', 'closeDay' => '2026-10-30', 'priceToBook' => null, 'dividendYield12m' => null,
            'netAssets' => null, 'favorite' => false,
        ]));
});

it('uses the book value in force on the close day', function () {
    listedFund('AAAA11', '2026-10-30', '20.00');
    listedReport('AAAA11', '11111111000111', ['month' => '2026-08', 'delivered_on' => '2026-09-10', 'book_value' => '10.00']);
    listedReport('AAAA11', '11111111000111', ['month' => '2026-09', 'delivered_on' => '2026-10-31', 'book_value' => '40.00']);

    $this->get('/fundos')->assertInertia(fn ($page) => $page->where('funds.0.priceToBook', '2'));
});

it('leaves out funds that did not trade in the last 10 business days', function (string $lastClose, bool $listed) {
    listedFund('AAAA11', '2026-10-30');
    listedFund('OLD11', $lastClose);
    listedFund('NONE11', null);

    $this->get('/fundos')->assertInertia(fn ($page) => $page
        ->has('funds', $listed ? 2 : 1)
        ->where('funds.0.ticker', 'AAAA11'));
})->with([
    'no limite' => ['2026-10-19', true],
    'um dia útil antes do limite' => ['2026-10-16', false],
]);

it('takes the latest close of each fund and sets closesOn to the most recent imported B3 day', function () {
    listedFund('AAAA11', '2026-10-28', '8.00');
    DailyPriceModel::create(['ticker' => 'AAAA11', 'day' => '2026-10-27', 'close' => '7.00']);
    listedFund('BBBB11', '2026-10-29', '5.00');
    ExchangeImportModel::create(['day' => '2026-10-28']);
    ExchangeImportModel::create(['day' => '2026-10-29']);

    $this->get('/fundos')->assertInertia(fn ($page) => $page
        ->where('closesOn', '2026-10-29')
        ->where('funds.0.close', '8')
        ->where('funds.0.closeDay', '2026-10-28'));
});

it('does not cite the B3 for a day only the brapi recorded', function () {
    listedFund('AAAA11', '2026-10-30');
    listedFund('BBBB11', '2026-10-29');
    ExchangeImportModel::create(['day' => '2026-10-29']);

    $this->get('/fundos')->assertInertia(fn ($page) => $page->where('closesOn', '2026-10-29'));
});

it('sends closesOn null when no B3 day was imported', function () {
    listedFund('AAAA11', '2026-10-30');

    $this->get('/fundos')->assertInertia(fn ($page) => $page->has('funds', 1)->where('closesOn', null));
});

it('sends closesOn null when no fund is listed', function () {
    $this->get('/fundos')->assertInertia(fn ($page) => $page->where('closesOn', null)->where('funds', []));
});

it('gives an ambiguous ticker no P/VP and no kind', function () {
    listedFund('AAAA11');
    listedReport('AAAA11', '11111111000111');
    listedReport('AAAA11', '22222222000122');

    $this->get('/fundos')->assertInertia(fn ($page) => $page
        ->where('funds.0.priceToBook', null)
        ->where('funds.0.kind', null)
        ->where('funds.0.dividendYield12m', null)
        ->where('funds.0.netAssets', null));
});

it('does not call any source', function () {
    listedFund('AAAA11');

    $this->get('/fundos')->assertOk();

    Http::assertNothingSent();
});

function listQueryCount(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get('/fundos')->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('runs the same number of queries with 3 and with 30 funds', function () {
    foreach (range(1, 3) as $n) {
        $ticker = sprintf('FND%02d11', $n);
        listedFund($ticker);
        listedReport($ticker, sprintf('%014d', $n), ['dividend_yield' => '0.005']);
    }
    FavoriteModel::create(['ticker' => 'FND0111']);
    $few = listQueryCount();

    foreach (range(4, 30) as $n) {
        $ticker = sprintf('FND%02d11', $n);
        listedFund($ticker);
        listedReport($ticker, sprintf('%014d', $n), ['dividend_yield' => '0.005']);
    }
    $many = listQueryCount();

    expect($many)->toBe($few);
});

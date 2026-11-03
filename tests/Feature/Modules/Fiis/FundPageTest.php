<?php

use App\Modules\Fiis\Application\EvaluateFavorites;
use App\Modules\Fiis\Infrastructure\Persistence\AlertModel;
use App\Modules\Fiis\Infrastructure\Persistence\DailyPriceModel;
use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundReportModel;
use App\Modules\Fiis\Infrastructure\Persistence\QuoteModel;
use App\Modules\Fiis\Infrastructure\Persistence\RuleStateModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Carbon::setTestNow('2026-10-30 15:00:00-03:00');
    FundModel::create(['ticker' => 'MXRF11', 'name' => 'Maxi Renda']);
    FavoriteModel::create(['ticker' => 'MXRF11']);
});
afterEach(fn () => Carbon::setTestNow());

function storeQuote(string $price = '9.42', string $change = '-0.5', string $at = '2026-10-30T14:30:00-03:00'): void
{
    QuoteModel::query()->updateOrCreate(['ticker' => 'MXRF11'], ['price' => $price, 'change_percent' => $change, 'quoted_at' => $at]);
}

it('starts new favorites automatic with sharp drop on', function () {
    $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page
        ->component('funds/show')
        ->where('fund.ticker', 'MXRF11')
        ->where('settings', ['mode' => 'automatic', 'target' => null, 'rangeMin' => null, 'rangeMax' => null, 'sharpDrop' => true]));
});

it('does not show a fund that is not a favorite', function () {
    $this->get('/fundos/ABCD11')->assertNotFound();
});

it('explains why the automatic rule cannot run', function () {
    storeQuote();

    $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page
        ->where('rules.0.rule', 'automatic')
        ->where('rules.0.matched', false)
        ->where('rules.0.unavailableReason', 'VP indisponível: sem o informe da CVM não dá para calcular o P/VP.'));
});

it('saves a target, normalizing the comma, and alerts right away', function () {
    storeQuote();

    $this->put('/fundos/MXRF11/regras', ['mode' => 'target', 'target' => '9,50', 'sharpDrop' => true])->assertRedirect('/fundos/MXRF11');

    $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page
        ->where('settings', ['mode' => 'target', 'target' => '9.5', 'rangeMin' => null, 'rangeMax' => null, 'sharpDrop' => true])
        ->where('rules.0.rule', 'price_target')
        ->where('rules.0.matched', true));

    expect(AlertModel::count())->toBe(1);
});

it('saves a range and clears the old target', function () {
    $this->put('/fundos/MXRF11/regras', ['mode' => 'target', 'target' => '9.50', 'sharpDrop' => false]);
    $this->put('/fundos/MXRF11/regras', ['mode' => 'range', 'rangeMin' => '9,00', 'rangeMax' => '9,60', 'sharpDrop' => false])->assertRedirect();

    $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page
        ->where('settings', ['mode' => 'range', 'target' => null, 'rangeMin' => '9', 'rangeMax' => '9.6', 'sharpDrop' => false]));
});

it('rejects invalid settings with a useful message and keeps the current ones', function (array $payload, string $field) {
    $this->put('/fundos/MXRF11/regras', ['sharpDrop' => true, ...$payload])->assertSessionHasErrors($field);

    $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page->where('settings.mode', 'automatic'));
})->with([
    'modo desconhecido' => [['mode' => 'qualquer'], 'mode'],
    'alvo vazio' => [['mode' => 'target', 'target' => ''], 'target'],
    'alvo zero' => [['mode' => 'target', 'target' => '0'], 'target'],
    'alvo negativo' => [['mode' => 'target', 'target' => '-1'], 'target'],
    'alvo em texto' => [['mode' => 'target', 'target' => 'abc'], 'target'],
    'milhar com ponto e vírgula' => [['mode' => 'target', 'target' => '1.234,50'], 'target'],
    'faixa invertida' => [['mode' => 'range', 'rangeMin' => '10', 'rangeMax' => '9'], 'rangeMax'],
    'faixa sem mínimo' => [['mode' => 'range', 'rangeMax' => '9'], 'rangeMin'],
]);

it('does not accept settings for a fund that is not a favorite', function () {
    $this->put('/fundos/ABCD11/regras', ['mode' => 'automatic', 'sharpDrop' => true])->assertNotFound();
});

it('forgets the rule cycle when a favorite is removed', function () {
    storeQuote();
    $this->put('/fundos/MXRF11/regras', ['mode' => 'target', 'target' => '9.50', 'sharpDrop' => false]);
    expect(RuleStateModel::count())->toBe(1);

    $this->delete('/favoritos/MXRF11');

    expect(RuleStateModel::count())->toBe(0)
        ->and(AlertModel::count())->toBe(1);
});

it('lists alerts, newest first, with what the next modules need', function () {
    storeQuote();
    $this->put('/fundos/MXRF11/regras', ['mode' => 'target', 'target' => '9.50', 'sharpDrop' => false]);

    $this->get('/avisos')->assertInertia(fn ($page) => $page
        ->component('alerts')
        ->has('alerts', 1)
        ->where('alerts.0.ticker', 'MXRF11')
        ->where('alerts.0.rule', 'price_target')
        ->where('alerts.0.price', '9.42')
        ->where('alerts.0.reference', 'alvo R$ 9,50')
        ->where('alerts.0.reason', 'Preço R$ 9,42 no alvo R$ 9,50')
        ->where('alerts.0.occurredAt', '2026-10-30T15:00:00-03:00')
        ->where('alerts.0.quotedAt', '2026-10-30T14:30:00-03:00'));
});

it('shows an empty alert list', function () {
    $this->get('/avisos')->assertInertia(fn ($page) => $page->where('alerts', []));
});

it('shows which rule holds on the dashboard', function () {
    storeQuote('9.42', '-3.5');
    $this->put('/fundos/MXRF11/regras', ['mode' => 'target', 'target' => '9.50', 'sharpDrop' => true]);

    $this->get('/')->assertInertia(fn ($page) => $page->where('favorites.0.matchedRules', ['price_target', 'sharp_drop']));
});

it('evaluates after refreshing, only once per quote', function () {
    $this->put('/fundos/MXRF11/regras', ['mode' => 'target', 'target' => '9.50', 'sharpDrop' => false]);
    Http::fake(['*' => Http::response(['results' => [['symbol' => 'MXRF11', 'data' => [
        'regularMarketPrice' => 9.42, 'regularMarketTime' => '2026-10-30T17:30:00.000Z',
    ]]]])]);

    $this->artisan('fiis:refresh-quotes')->assertSuccessful();
    $this->artisan('fiis:refresh-quotes')->assertSuccessful();

    expect(AlertModel::count())->toBe(1);
});

it('does not fire on the old quote when a favorite is removed and added again', function () {
    storeQuote('9.42');
    $this->put('/fundos/MXRF11/regras', ['mode' => 'automatic', 'sharpDrop' => true]);
    Http::fake(['*/stocks/historical*' => Http::response('', 500)]);

    $this->delete('/favoritos/MXRF11');
    $this->post('/favoritos', ['ticker' => 'MXRF11']);
    $this->put('/fundos/MXRF11/regras', ['mode' => 'target', 'target' => '9.50', 'sharpDrop' => false]);
    app(EvaluateFavorites::class)->handle();

    expect(AlertModel::count())->toBe(0)
        ->and(QuoteModel::find('MXRF11'))->toBeNull();
});

it('survives a leap day: the year before is a real date', function () {
    Carbon::setTestNow('2028-02-29 15:00:00-03:00');
    storeQuote('9.42', '-1', '2028-02-29T14:00:00-03:00');

    $this->get('/fundos/MXRF11')->assertOk();
    app(EvaluateFavorites::class)->handle();
    expect(FavoriteModel::find(FavoriteModel::first()->id)->last_evaluated_quote_at)->not->toBeNull();
});

function storeReport(array $attributes = []): void
{
    FundReportModel::create([
        'cnpj' => '11111111000111', 'ticker' => 'MXRF11', 'month' => '2026-09', 'version' => 1,
        'delivered_on' => '2026-10-10', 'book_value' => '10.00', 'dividend_yield' => '0.008',
        ...$attributes,
    ]);
}

function storeClose(string $day, string $close): void
{
    DailyPriceModel::create(['ticker' => 'MXRF11', 'day' => $day, 'close' => $close]);
}

it('sends the fund profile from the latest report', function () {
    storeReport(['month' => '2026-08', 'segment' => 'Papel', 'holders' => 100]);
    storeReport(['month' => '2026-09', 'segment' => 'Híbrido', 'administrator' => 'BTG', 'started_on' => '2019-03-04', 'net_assets' => '2500000000.50', 'holders' => 900000]);
    storeReport(['month' => '2026-10', 'segment' => null, 'delivered_on' => '2026-10-29']);

    $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page->where('fund.profile', [
        'month' => '2026-09', 'segment' => 'Híbrido', 'administrator' => 'BTG',
        'startedOn' => '2019-03-04', 'netAssets' => '2500000000.50', 'holders' => 900000,
    ]));
});

it('sends a year of closes with the daily P/VP', function () {
    storeQuote();
    storeReport();
    storeClose('2026-10-05', '9.00');
    storeClose('2026-10-15', '9.50');
    storeClose('2026-10-20', '10.00');

    $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page
        ->has('fund.history', 3)
        ->where('fund.history.0', ['date' => '2026-10-05', 'close' => '9', 'priceToBook' => null])
        ->where('fund.history.1', ['date' => '2026-10-15', 'close' => '9.5', 'priceToBook' => '0.95'])
        ->where('fund.history.2', ['date' => '2026-10-20', 'close' => '10', 'priceToBook' => '1']));
});

it('says the 12 month average is still being collected', function () {
    storeQuote();
    storeClose('2026-07-30', '9.00');
    storeClose('2026-09-01', '9.50');
    storeClose('2026-10-20', '10.00');

    $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page
        ->where('fund.average12m', ['months' => 12, 'monthsStored' => 3, 'value' => null])
        ->where('fund.average6m', ['months' => 6, 'monthsStored' => 3, 'value' => null]));
});

it('fetches closes a few days before the window start so a non-trading start still completes it', function () {
    storeQuote();
    storeClose('2025-10-27', '8.00');
    storeClose('2026-10-20', '10.00');

    $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page
        ->where('fund.average12m', ['months' => 12, 'monthsStored' => 12, 'value' => '10']));
});

it('sends estimated dividends per share', function () {
    storeReport();

    $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page
        ->has('fund.dividends', 1)
        ->where('fund.dividends.0', ['month' => '2026-09', 'perShare' => '0.08']));
});

it('opens without quote, reports or history', function () {
    $this->get('/fundos/MXRF11')->assertOk()->assertInertia(fn ($page) => $page
        ->where('fund.price', null)
        ->where('fund.profile', null)
        ->where('fund.history', [])
        ->where('fund.dividends', [])
        ->where('fund.average12m.value', null));
});

it('sends no profile or dividends when the ticker is ambiguous', function () {
    storeReport(['segment' => 'Papel']);
    storeReport(['cnpj' => '22222222000122', 'segment' => 'Tijolo']);

    $this->get('/fundos/MXRF11')->assertOk()->assertInertia(fn ($page) => $page
        ->where('fund.profile', null)
        ->where('fund.dividends', []));
});

it('keeps the dashboard light', function () {
    storeQuote();
    storeReport(['segment' => 'Papel']);
    storeClose('2026-10-20', '10.00');

    $this->get('/')->assertInertia(fn ($page) => $page
        ->missing('favorites.0.history')
        ->missing('favorites.0.dividends')
        ->missing('favorites.0.profile')
        ->missing('favorites.0.average12m'));
});

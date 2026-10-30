<?php

use App\Modules\Fiis\Application\EvaluateFavorites;
use App\Modules\Fiis\Infrastructure\Persistence\AlertModel;
use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundModel;
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

it('survives a leap day: the year before is a real date', function () {
    Carbon::setTestNow('2028-02-29 15:00:00-03:00');
    storeQuote('9.42', '-1', '2028-02-29T14:00:00-03:00');

    $this->get('/fundos/MXRF11')->assertOk();
    app(EvaluateFavorites::class)->handle();
    expect(FavoriteModel::find(FavoriteModel::first()->id)->last_evaluated_quote_at)->not->toBeNull();
});

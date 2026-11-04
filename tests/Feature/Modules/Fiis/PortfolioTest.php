<?php

use App\Modules\Fiis\Infrastructure\Persistence\DailyPriceModel;
use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundModel;
use App\Modules\Fiis\Infrastructure\Persistence\HoldingModel;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-30 15:00:00-03:00');
    FundModel::create(['ticker' => 'KNRI11', 'name' => 'Kinea Renda Imobiliaria']);
    FundModel::create(['ticker' => 'MXRF11', 'name' => 'Maxi Renda']);
});
afterEach(fn () => Carbon::setTestNow());

it('shows an empty portfolio', function () {
    $this->get('/carteira')->assertInertia(fn ($page) => $page
        ->component('portfolio')
        ->where('rows', [])
        ->where('totals', ['value' => null, 'lastIncome' => null, 'averageIncome' => null, 'withoutPrice' => 0, 'withoutDividends' => 0]));
});

it('shows rows and totals as decimal strings', function () {
    DailyPriceModel::create(['ticker' => 'KNRI11', 'day' => '2026-10-29', 'close' => '160.00']);
    HoldingModel::create(['ticker' => 'KNRI11', 'shares' => 10]);
    HoldingModel::create(['ticker' => 'MXRF11', 'shares' => 5]);

    $this->get('/carteira')->assertInertia(fn ($page) => $page
        ->where('rows.0', [
            'ticker' => 'KNRI11', 'name' => 'Kinea Renda Imobiliaria', 'shares' => 10,
            'price' => '160', 'quotedAt' => null, 'closeDay' => '2026-10-29',
            'positionValue' => '1600', 'lastIncome' => null, 'averageIncome' => null, 'dividendMonths' => 0,
        ])
        ->where('rows.1.ticker', 'MXRF11')
        ->where('rows.1.price', null)
        ->where('totals.value', '1600')
        ->where('totals.withoutPrice', 1)
        ->where('totals.withoutDividends', 2));
});

it('saves and updates the shares, with the ticker in upper case', function () {
    $this->put('/carteira/knri11', ['shares' => '10'])->assertRedirect('/carteira');
    $this->put('/carteira/KNRI11', ['shares' => 100, 'stay' => true])->assertRedirect();

    expect(HoldingModel::query()->pluck('shares', 'ticker')->all())->toBe(['KNRI11' => 100]);
});

it('rejects shares that are not a positive integer', function (mixed $shares) {
    $this->put('/carteira/KNRI11', ['shares' => $shares])
        ->assertSessionHasErrors(['shares' => 'Informe um número inteiro de cotas, como 100.']);

    expect(HoldingModel::query()->count())->toBe(0);
})->with(['zero' => 0, 'negativo' => -1, 'decimal' => '1.5', 'texto' => 'abc', 'vazio' => '']);

it('rejects a fund outside the catalog', function () {
    $this->put('/carteira/XPTO11', ['shares' => 10])
        ->assertSessionHasErrors(['ticker' => 'Esse fundo não está no catálogo. Confira o código na B3.']);
});

it('removes a fund and answers 404 for one that is not there', function () {
    HoldingModel::create(['ticker' => 'KNRI11', 'shares' => 10]);

    $this->delete('/carteira/knri11')->assertRedirect('/carteira');
    $this->delete('/carteira/KNRI11')->assertNotFound();
    expect(HoldingModel::query()->count())->toBe(0);
});

it('keeps favorites and portfolio apart', function () {
    $this->post('/favoritos', ['ticker' => 'KNRI11', 'stay' => true]);
    $this->put('/carteira/MXRF11', ['shares' => 10]);

    expect(HoldingModel::query()->pluck('ticker')->all())->toBe(['MXRF11'])
        ->and(FavoriteModel::query()->pluck('ticker')->all())->toBe(['KNRI11']);
});

it('searches the catalog and marks what is in the portfolio', function () {
    HoldingModel::create(['ticker' => 'KNRI11', 'shares' => 10]);

    $this->get('/carteira?q=kinea')->assertInertia(fn ($page) => $page
        ->where('search.query', 'kinea')
        ->where('search.results', [['ticker' => 'KNRI11', 'name' => 'Kinea Renda Imobiliaria', 'inPortfolio' => true]]));
});

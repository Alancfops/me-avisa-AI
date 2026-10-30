<?php

use App\Modules\Fiis\Infrastructure\Persistence\DailyPriceModel;
use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundModel;
use App\Modules\Fiis\Infrastructure\Persistence\QuoteModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

function fixtureBody(string $name): string
{
    return (string) file_get_contents(__DIR__."/../../../Fixtures/brapi/{$name}.json");
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-30 15:00:00-03:00');
    FundModel::create(['ticker' => 'KNRI11', 'name' => 'Kinea Renda Imobiliaria']);
    FundModel::create(['ticker' => 'MXRF11', 'name' => 'Maxi Renda']);
});
afterEach(fn () => Carbon::setTestNow());

it('searches the catalog by ticker or name and marks favorites', function (string $query, array $tickers) {
    FavoriteModel::create(['ticker' => 'KNRI11']);

    $this->get('/?q='.urlencode($query))->assertInertia(fn ($page) => $page
        ->where('search.query', $query)
        ->where('search.results', $tickers));
})->with([
    'ticker minúsculo' => ['mxrf', [['ticker' => 'MXRF11', 'name' => 'Maxi Renda', 'favorite' => false]]],
    'nome' => ['kinea', [['ticker' => 'KNRI11', 'name' => 'Kinea Renda Imobiliaria', 'favorite' => true]]],
    'curinga não é curinga' => ['%', []],
    'vazio' => ['', []],
]);

it('tells the screen when the catalog is empty', function () {
    FundModel::query()->delete();

    $this->get('/')->assertInertia(fn ($page) => $page->where('search.catalogEmpty', true));
});

it('favorites a fund and stores three months of closes, without todays open bar', function () {
    Http::fake(['*/stocks/historical*' => Http::response(fixtureBody('historical-knri11'))]);

    $this->post('/favoritos', ['ticker' => ' knri11 '])->assertRedirect();

    expect(FavoriteModel::where('ticker', 'KNRI11')->exists())->toBeTrue()
        ->and(DailyPriceModel::where('ticker', 'KNRI11')->orderBy('day')->pluck('close', 'day')->map(fn ($c) => (float) $c)->all())
        ->toBe(['2026-08-04' => 152.09, '2026-10-29' => 149.6]);
});

it('keeps the favorite when the history fails, and tries again later', function () {
    Http::fake(['*/stocks/historical*' => Http::response('', 500)]);

    $this->post('/favoritos', ['ticker' => 'KNRI11'])->assertRedirect();

    expect(FavoriteModel::where('ticker', 'KNRI11')->exists())->toBeTrue()
        ->and(DailyPriceModel::count())->toBe(0);
});

it('does not favorite twice', function () {
    Http::fake(['*/stocks/historical*' => Http::response(fixtureBody('historical-knri11'))]);

    $this->post('/favoritos', ['ticker' => 'KNRI11']);
    $this->post('/favoritos', ['ticker' => 'KNRI11'])->assertRedirect();

    expect(FavoriteModel::count())->toBe(1);
    Http::assertSentCount(1);
});

it('rejects a ticker outside the catalog', function (string $ticker) {
    Http::fake();

    $this->post('/favoritos', ['ticker' => $ticker])->assertSessionHasErrors('ticker');

    expect(FavoriteModel::count())->toBe(0);
    Http::assertNothingSent();
})->with(['ABCD11', '', "'; DROP TABLE favorites; --"]);

it('removes a favorite', function () {
    FavoriteModel::create(['ticker' => 'KNRI11']);

    $this->delete('/favoritos/KNRI11')->assertRedirect();

    expect(FavoriteModel::count())->toBe(0);
});

it('shows the stored quote with the time reported by the source', function () {
    FavoriteModel::create(['ticker' => 'KNRI11']);
    QuoteModel::create([
        'ticker' => 'KNRI11', 'price' => '149.910000', 'change_percent' => '-0.210000',
        'volume' => 27898, 'quoted_at' => '2026-10-30T17:57:30Z',
    ]);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('favorites.0.ticker', 'KNRI11')
        ->where('favorites.0.name', 'Kinea Renda Imobiliaria')
        ->where('favorites.0.price', '149.91')
        ->where('favorites.0.changePercent', '-0.21')
        ->where('favorites.0.quotedAt', '2026-10-30T14:57:30-03:00'));
});

it('shows missing data as null, never zero', function () {
    FavoriteModel::create(['ticker' => 'KNRI11']);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('favorites.0.price', null)
        ->where('favorites.0.changePercent', null)
        ->where('favorites.0.quotedAt', null));
});

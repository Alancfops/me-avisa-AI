<?php

use App\Modules\Fiis\Application\FundCatalog;
use App\Modules\Fiis\Application\SourceUnavailable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function catalogFixture(string $name): string
{
    return (string) file_get_contents(__DIR__."/../../../../Fixtures/brapi/{$name}.json");
}

it('walks every page, normalizes tickers and skips invalid or inactive funds', function () {
    Http::fake([
        '*page=1*' => Http::response(catalogFixture('tickers-page1')),
        '*page=2*' => Http::response(catalogFixture('tickers-page2')),
    ]);

    $entries = app(FundCatalog::class)->all();

    expect(array_map(fn ($e) => [$e->ticker, $e->name], $entries))->toBe([
        ['MXRF11', 'Maxi Renda Fundo de Investimento Imobiliario Cotas'],
        ['KNRI11', 'KINEA RENDA'],
        ['VISC11', 'Vinci Shopping Centers'],
    ]);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/v2/tickers?type=fund&subType=fii'));
});

it('fails as a whole when a page breaks, so a partial list never looks complete', function () {
    Http::fake([
        '*page=1*' => Http::response(catalogFixture('tickers-page1')),
        '*page=2*' => Http::response('quebrado'),
    ]);

    expect(fn () => app(FundCatalog::class)->all())->toThrow(SourceUnavailable::class);
});

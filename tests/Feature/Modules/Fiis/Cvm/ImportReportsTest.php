<?php

use App\Modules\Fiis\Application\FundReports;
use App\Modules\Fiis\Application\SourceUnavailable;
use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundReportModel;
use App\Modules\Fiis\Infrastructure\Persistence\QuoteModel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

function cvmZip(?string $geral = null, ?string $complemento = null, int $year = 2026): string
{
    $fixtures = __DIR__.'/../../../../Fixtures/cvm';
    $path = tempnam(sys_get_temp_dir(), 'cvm');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString("inf_mensal_fii_geral_{$year}.csv", $geral ?? (string) file_get_contents("{$fixtures}/geral.csv"));
    $zip->addFromString("inf_mensal_fii_complemento_{$year}.csv", $complemento ?? (string) file_get_contents("{$fixtures}/complemento.csv"));
    $zip->addFromString("inf_mensal_fii_ativo_passivo_{$year}.csv", 'não usado');
    $zip->close();
    $content = (string) file_get_contents($path);
    unlink($path);

    return $content;
}

function fakeCvm(?string $current = null, mixed $previous = 'vazio'): void
{
    Http::fake([
        '*inf_mensal_fii_2026.zip' => Http::response($current ?? cvmZip()),
        '*inf_mensal_fii_2025.zip' => $previous === 'vazio'
            ? Http::response(cvmZip("Tipo_Fundo_Classe;CNPJ_Fundo_Classe;Data_Referencia;Versao;Data_Entrega;Nome_Fundo_Classe;Codigo_ISIN;Segmento_Atuacao\n", "CNPJ_Fundo_Classe;Data_Referencia;Versao;Valor_Patrimonial_Cotas;Percentual_Dividend_Yield_Mes\n", 2025))
            : $previous,
    ]);
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-30 15:00:00-03:00');
    foreach (['MXRF11', 'XPML11', 'KNRI11', 'VISC11', 'HGLG11'] as $ticker) {
        FundModel::create(['ticker' => $ticker, 'name' => $ticker]);
    }
});
afterEach(fn () => Carbon::setTestNow());

it('reads Latin-1 CSVs with semicolons, keeping the highest version of each month', function () {
    fakeCvm();

    $records = app(FundReports::class)->forYear(2026);
    $september = array_values(array_filter($records, fn ($r) => $r->cnpj === '97.521.225/0001-25' && $r->month === '2026-09'));

    expect($september)->toHaveCount(1)
        ->and($september[0]->version)->toBe(2)
        ->and($september[0]->deliveredOn)->toBe('2026-10-20')
        ->and($september[0]->bookValue)->toBe('9.60')
        ->and($september[0]->dividendYield)->toBe('0.011')
        ->and($september[0]->isin)->toBe('BRMXRFCTF008');
});

it('drops rows without a delivery date, which have no moment to take effect', function () {
    fakeCvm();

    $records = app(FundReports::class)->forYear(2026);

    expect(array_column($records, 'cnpj'))->not->toContain('44.444.444/0001-44');
});

it('keeps missing or non positive book value as null, never zero', function () {
    fakeCvm();

    $records = collect(app(FundReports::class)->forYear(2026))->keyBy('cnpj');

    expect($records['55.555.555/0001-55']->bookValue)->toBeNull()
        ->and($records['55.555.555/0001-55']->dividendYield)->toBeNull()
        ->and($records['66.666.666/0001-66']->bookValue)->toBeNull();
});

it('rejects a broken file', function (Closure $body, int $status) {
    Http::fake(['*' => Http::response($body(), $status)]);

    expect(fn () => app(FundReports::class)->forYear(2026))->toThrow(SourceUnavailable::class);
})->with([
    'não é zip' => [fn () => '<html>manutenção</html>', 200],
    'status 500' => [fn () => '', 500],
    'zip sem colunas' => [fn () => cvmZip("a;b\n1;2\n", "a;b\n1;2\n"), 200],
]);

it('imports only funds in the catalog, keyed by the ISIN root', function () {
    fakeCvm();

    $this->artisan('fiis:import-reports')->assertSuccessful();

    expect(FundReportModel::where('ticker', 'MXRF11')->count())->toBe(2)
        ->and(FundReportModel::where('cnpj', '11.111.111/0001-11')->exists())->toBeFalse()
        ->and(FundReportModel::where('cnpj', '22.222.222/0001-22')->exists())->toBeFalse();
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'inf_mensal_fii_2025.zip'));
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'inf_mensal_fii_2026.zip'));
});

it('never downgrades a report to an older version', function () {
    fakeCvm();
    $this->artisan('fiis:import-reports')->assertSuccessful();
    FundReportModel::where('cnpj', '97.521.225/0001-25')->where('month', '2026-09')->update(['version' => 3, 'book_value' => '9.99']);

    $this->artisan('fiis:import-reports')->assertSuccessful();

    expect((float) FundReportModel::where('cnpj', '97.521.225/0001-25')->where('month', '2026-09')->value('book_value'))->toBe(9.99);
});

it('keeps what it had when a year fails, and imports the other', function () {
    fakeCvm(previous: Http::response('', 500));

    $this->artisan('fiis:import-reports')->assertSuccessful();

    expect(FundReportModel::where('ticker', 'MXRF11')->exists())->toBeTrue();
});

it('fails loudly and keeps everything when no year comes through', function () {
    FundReportModel::create(['cnpj' => '1', 'ticker' => 'MXRF11', 'month' => '2026-01', 'version' => 1, 'delivered_on' => '2026-02-01', 'book_value' => '9']);
    Http::fake(['*' => Http::response('', 500)]);

    $this->artisan('fiis:import-reports')->assertFailed();

    expect(FundReportModel::count())->toBe(1);
});

describe('on the fund page', function () {
    beforeEach(function () {
        FavoriteModel::create(['ticker' => 'MXRF11']);
        QuoteModel::create(['ticker' => 'MXRF11', 'price' => '9.00', 'change_percent' => '-1', 'quoted_at' => '2026-10-30T14:00:00-03:00']);
    });

    it('computes P/VP from the latest delivered report and names its month', function () {
        fakeCvm();
        $this->artisan('fiis:import-reports');

        $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page
            ->where('fund.priceToBook', '0.9375')
            ->where('fund.bookValueMonth', '2026-09'));
    });

    it('does not use a report before its delivery date', function () {
        fakeCvm();
        $this->artisan('fiis:import-reports');
        FundReportModel::where('month', '2026-09')->where('ticker', 'MXRF11')->update(['delivered_on' => '2026-11-01']);

        $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page
            ->where('fund.bookValueMonth', '2026-08')
            ->where('fund.priceToBook', '0.957447'));
    });

    it('says the book value is unavailable when nothing matched', function () {
        $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page
            ->where('fund.priceToBook', null)
            ->where('fund.bookValueMonth', null)
            ->where('rules.0.unavailableReason', 'VP indisponível: sem o informe da CVM não dá para calcular o P/VP.'));
    });

    it('has no DY 12m until 12 months exist', function () {
        fakeCvm();
        $this->artisan('fiis:import-reports');

        $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page->where('fund.dividendYield12m', null));
    });

    it('shows the DY 12m as a fraction', function () {
        foreach (range(1, 12) as $i) {
            FundReportModel::create(['cnpj' => '1', 'ticker' => 'MXRF11', 'month' => sprintf('2025-%02d', $i), 'version' => 1, 'delivered_on' => sprintf('2025-%02d-15', $i), 'book_value' => '10', 'dividend_yield' => '0.01']);
        }

        $this->get('/fundos/MXRF11')->assertInertia(fn ($page) => $page->where('fund.dividendYield12m', '0.12'));
    });
});

// O ISIN é preenchido pelo administrador e há fundos com o ISIN de outro. No arquivo
// real de 2026, PENINSULA declara o ISIN do XPML11, com VP de R$ 22.548 contra R$ 107.
it('leaves a ticker claimed by two funds without book value, instead of guessing', function () {
    fakeCvm();
    $this->artisan('fiis:import-reports')->assertSuccessful();
    FavoriteModel::create(['ticker' => 'XPML11']);
    QuoteModel::create(['ticker' => 'XPML11', 'price' => '100', 'quoted_at' => '2026-10-30T14:00:00-03:00']);

    $this->get('/fundos/XPML11')->assertInertia(fn ($page) => $page
        ->where('fund.priceToBook', null)
        ->where('fund.dividendYield12m', null)
        ->where('rules.0.unavailableReason', 'VP indisponível: sem o informe da CVM não dá para calcular o P/VP.'));
});

it('reports the ambiguous tickers when importing', function () {
    fakeCvm();

    $this->artisan('fiis:import-reports')
        ->expectsOutputToContain('XPML11')
        ->assertSuccessful();
});

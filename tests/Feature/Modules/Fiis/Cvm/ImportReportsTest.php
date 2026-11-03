<?php

use App\Modules\Fiis\Application\FundReports;
use App\Modules\Fiis\Application\ReportRepository;
use App\Modules\Fiis\Application\SourceUnavailable;
use App\Modules\Fiis\Domain\FundKind;
use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundReportModel;
use App\Modules\Fiis\Infrastructure\Persistence\QuoteModel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function cvmZip(?string $geral = null, ?string $complemento = null, int $year = 2026, string|false|null $ativoPassivo = null): string
{
    $fixtures = __DIR__.'/../../../../Fixtures/cvm';
    $path = tempnam(sys_get_temp_dir(), 'cvm');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString("inf_mensal_fii_geral_{$year}.csv", $geral ?? (string) file_get_contents("{$fixtures}/geral.csv"));
    $zip->addFromString("inf_mensal_fii_complemento_{$year}.csv", $complemento ?? (string) file_get_contents("{$fixtures}/complemento.csv"));
    if ($ativoPassivo !== false) {
        $zip->addFromString("inf_mensal_fii_ativo_passivo_{$year}.csv", $ativoPassivo ?? (string) file_get_contents("{$fixtures}/ativo_passivo.csv"));
    }
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

const CVM_GERAL_HEADER = "Tipo_Fundo_Classe;CNPJ_Fundo_Classe;Data_Referencia;Versao;Data_Entrega;Nome_Fundo_Classe;Codigo_ISIN;Segmento_Atuacao\n";
const CVM_COMPLEMENTO_HEADER = "CNPJ_Fundo_Classe;Data_Referencia;Versao;Valor_Patrimonial_Cotas;Percentual_Dividend_Yield_Mes\n";

it('keeps the last valid book value when a corrected report comes without one', function () {
    fakeCvm(cvmZip(
        CVM_GERAL_HEADER."Classe;1;2026-09-01;1;2026-10-10;MAXI;BRMXRFCTF008;x\nClasse;1;2026-09-01;2;2026-10-20;MAXI;BRMXRFCTF008;x\n",
        CVM_COMPLEMENTO_HEADER."1;2026-09-01;1;9.50;0.01\n1;2026-09-01;2;;\n",
    ));

    $this->artisan('fiis:import-reports')->assertSuccessful();
    $row = FundReportModel::where('cnpj', '1')->first();

    expect((float) $row->book_value)->toBe(9.5)
        ->and($row->version)->toBe(1)
        ->and(substr((string) $row->delivered_on, 0, 10))->toBe('2026-10-10');
});

it('does not erase a stored book value with a later file that has none', function () {
    FundReportModel::create(['cnpj' => '1', 'ticker' => 'MXRF11', 'month' => '2026-09', 'version' => 1, 'delivered_on' => '2026-10-10', 'book_value' => '9.50']);
    fakeCvm(cvmZip(
        CVM_GERAL_HEADER."Classe;1;2026-09-01;2;2026-10-20;MAXI;BRMXRFCTF008;x\n",
        CVM_COMPLEMENTO_HEADER."1;2026-09-01;2;;\n",
    ));

    $this->artisan('fiis:import-reports')->assertSuccessful();

    expect((float) FundReportModel::where('cnpj', '1')->value('book_value'))->toBe(9.5);
});

it('treats values outside the storable range as missing, instead of failing the whole year', function () {
    fakeCvm(cvmZip(
        CVM_GERAL_HEADER."Classe;1;2026-09-01;1;2026-10-10;MAXI;BRMXRFCTF008;x\n",
        CVM_COMPLEMENTO_HEADER."1;2026-09-01;1;9999999999999999;999999999\n",
    ));

    $this->artisan('fiis:import-reports')->assertSuccessful();
    $row = FundReportModel::where('cnpj', '1')->first();

    expect($row->book_value)->toBeNull()->and($row->dividend_yield)->toBeNull();
});

it('reads the fund profile from the latest version', function () {
    fakeCvm();

    $september = collect(app(FundReports::class)->forYear(2026))
        ->first(fn ($r) => $r->cnpj === '97.521.225/0001-25' && $r->month === '2026-09');

    expect($september->segment)->toBe('Títulos e Valores Mobiliários')
        ->and($september->administrator)->toBe('BTG PACTUAL SERVIÇOS FINANCEIROS S.A. DTVM')
        ->and($september->startedOn)->toBe('2012-08-01')
        ->and($september->netAssets)->toBe('3900000000.55')
        ->and($september->holders)->toBe(1250000);
});

it('imports a year whose files lack the profile columns', function () {
    fakeCvm(cvmZip(
        "Tipo_Fundo_Classe;CNPJ_Fundo_Classe;Data_Referencia;Versao;Data_Entrega;Nome_Fundo_Classe;Codigo_ISIN\nClasse;1;2026-09-01;1;2026-10-10;MAXI;BRMXRFCTF008\n",
        CVM_COMPLEMENTO_HEADER."1;2026-09-01;1;9.50;0.01\n",
    ));

    $records = app(FundReports::class)->forYear(2026);

    expect($records)->toHaveCount(1)
        ->and($records[0]->bookValue)->toBe('9.50')
        ->and($records[0]->dividendYield)->toBe('0.01')
        ->and($records[0]->segment)->toBeNull()
        ->and($records[0]->administrator)->toBeNull()
        ->and($records[0]->startedOn)->toBeNull()
        ->and($records[0]->netAssets)->toBeNull()
        ->and($records[0]->holders)->toBeNull();
});

it('stores the profile columns', function () {
    fakeCvm();

    $this->artisan('fiis:import-reports')->assertSuccessful();
    $row = FundReportModel::where('ticker', 'MXRF11')->where('month', '2026-09')->first();

    expect($row->segment)->toBe('Títulos e Valores Mobiliários')
        ->and($row->administrator)->toBe('BTG PACTUAL SERVIÇOS FINANCEIROS S.A. DTVM')
        ->and(substr((string) $row->started_on, 0, 10))->toBe('2012-08-01')
        ->and((string) $row->net_assets)->toBe('3900000000.55')
        ->and($row->holders)->toBe(1250000);
});

it('turns an invalid profile value into null without dropping the row', function (string $started, string $netAssets, string $holders, array $expected) {
    $geral = "Tipo_Fundo_Classe;CNPJ_Fundo_Classe;Data_Referencia;Versao;Data_Entrega;Nome_Fundo_Classe;Codigo_ISIN;Segmento_Atuacao;Nome_Administrador;Data_Funcionamento\n"
        ."Classe;1;2026-09-01;1;2026-10-10;MAXI;BRMXRFCTF008;Shoppings;ADM;{$started}\n";
    $complemento = "CNPJ_Fundo_Classe;Data_Referencia;Versao;Patrimonio_Liquido;Valor_Patrimonial_Cotas;Percentual_Dividend_Yield_Mes;Total_Numero_Cotistas\n"
        ."1;2026-09-01;1;{$netAssets};9.50;0.01;{$holders}\n";
    fakeCvm(cvmZip($geral, $complemento));

    $records = app(FundReports::class)->forYear(2026);

    expect($records)->toHaveCount(1)
        ->and($records[0]->bookValue)->toBe('9.50')
        ->and($records[0]->segment)->toBe('Shoppings')
        ->and([$records[0]->startedOn, $records[0]->netAssets, $records[0]->holders])->toBe($expected);
})->with([
    'cotistas abc' => ['2012-08-01', '100.50', 'abc', ['2012-08-01', '100.50', null]],
    'data brasileira' => ['31/12/2010', '100.50', '10', [null, '100.50', 10]],
    'data impossível' => ['2012-02-30', '100.50', '10', [null, '100.50', 10]],
    'patrimônio negativo' => ['2012-08-01', '-5', '10', ['2012-08-01', null, 10]],
]);

it('stores the portfolio of the highest version', function () {
    fakeCvm();

    $this->artisan('fiis:import-reports')->assertSuccessful();
    $row = FundReportModel::where('ticker', 'MXRF11')->where('month', '2026-09')->first();

    expect($row->version)->toBe(2)
        ->and((string) $row->total_invested)->toBe('5176421015.19')
        ->and((string) $row->real_estate)->toBe('287878537.64')
        ->and((string) $row->receivables)->toBe('3892924050.06')
        ->and((string) $row->fii_shares)->toBe('718369408.35');
});

it('sums every receivable column', function () {
    $ativo = "CNPJ_Fundo_Classe;Data_Referencia;Versao;Total_Investido;Direitos_Bens_Imoveis;CRI;CRI_CRA;LCI;LCI_LCA;Letras_Hipotecarias;LIG;FII\n"
        ."97.521.225/0001-25;2026-09-01;2;100;0;1.10;2.20;3.30;4.40;5.50;6.60;0\n";
    fakeCvm(cvmZip(null, null, 2026, $ativo));

    $this->artisan('fiis:import-reports')->assertSuccessful();

    expect((string) FundReportModel::where('ticker', 'MXRF11')->where('month', '2026-09')->value('receivables'))->toBe('23.10');
});

it('imports book value and yield from a zip without the portfolio file, leaving it null', function () {
    fakeCvm(cvmZip(null, null, 2026, false));

    $this->artisan('fiis:import-reports')->assertSuccessful();
    $row = FundReportModel::where('ticker', 'MXRF11')->where('month', '2026-09')->first();

    expect((float) $row->book_value)->toBe(9.6)
        ->and((float) $row->dividend_yield)->toBe(0.011)
        ->and($row->total_invested)->toBeNull()
        ->and($row->real_estate)->toBeNull()
        ->and($row->receivables)->toBeNull()
        ->and($row->fii_shares)->toBeNull();
});

it('imports a year whose portfolio file has an unknown format, without portfolio', function () {
    Log::spy();
    fakeCvm(cvmZip(null, null, 2026, "a;b\n1;2\n"));

    $this->artisan('fiis:import-reports')->assertSuccessful();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'ativo_passivo'))->atLeast()->once();

    expect(FundReportModel::where('ticker', 'MXRF11')->where('month', '2026-09')->value('total_invested'))->toBeNull()
        ->and((float) FundReportModel::where('ticker', 'MXRF11')->where('month', '2026-09')->value('book_value'))->toBe(9.6);
});

it('keeps a stored portfolio when a later file brings none', function () {
    fakeCvm();
    $this->artisan('fiis:import-reports')->assertSuccessful();
    fakeCvm(cvmZip(null, null, 2026, false));

    $this->artisan('fiis:import-reports')->assertSuccessful();

    expect((string) FundReportModel::where('ticker', 'MXRF11')->where('month', '2026-09')->value('total_invested'))->toBe('5176421015.19');
});

it('stores a corrected book value even when the year brings no portfolio, keeping the stored composition', function () {
    FundReportModel::create(['cnpj' => '1', 'ticker' => 'MXRF11', 'month' => '2026-09', 'version' => 1, 'delivered_on' => '2026-10-10', 'book_value' => '9.50',
        'total_invested' => '100', 'real_estate' => '90', 'receivables' => '5', 'fii_shares' => '5']);
    fakeCvm(cvmZip(
        CVM_GERAL_HEADER."Classe;1;2026-09-01;2;2026-10-20;MAXI;BRMXRFCTF008;x\n",
        CVM_COMPLEMENTO_HEADER."1;2026-09-01;2;9.70;0.01\n",
        2026,
        false,
    ));

    $this->artisan('fiis:import-reports')->assertSuccessful();
    $row = FundReportModel::where('cnpj', '1')->first();

    expect($row->version)->toBe(2)
        ->and((float) $row->book_value)->toBe(9.7)
        ->and((string) $row->total_invested)->toBe('100.00')
        ->and((string) $row->real_estate)->toBe('90.00')
        ->and((string) $row->receivables)->toBe('5.00')
        ->and((string) $row->fii_shares)->toBe('5.00');
});

it('keeps the composition of the latest version that has one when a newer version has none', function () {
    $ativo = "CNPJ_Fundo_Classe;Data_Referencia;Versao;Total_Investido;Direitos_Bens_Imoveis;CRI;CRI_CRA;LCI;LCI_LCA;Letras_Hipotecarias;LIG;FII\n"
        ."1;2026-09-01;1;100;10;;;;;;;\n"
        ."1;2026-09-01;2;200;20;;;;;;;\n";
    fakeCvm(cvmZip(
        CVM_GERAL_HEADER."Classe;1;2026-09-01;1;2026-10-10;MAXI;BRMXRFCTF008;x\nClasse;1;2026-09-01;2;2026-10-12;MAXI;BRMXRFCTF008;x\nClasse;1;2026-09-01;3;2026-10-20;MAXI;BRMXRFCTF008;x\n",
        CVM_COMPLEMENTO_HEADER."1;2026-09-01;1;9.50;0.01\n1;2026-09-01;2;9.60;0.01\n1;2026-09-01;3;9.70;0.01\n",
        2026,
        $ativo,
    ));

    $records = app(FundReports::class)->forYear(2026);

    expect($records)->toHaveCount(1)
        ->and($records[0]->version)->toBe(3)
        ->and($records[0]->bookValue)->toBe('9.70')
        ->and($records[0]->portfolio?->totalInvested)->toBe('200.00');
});

it('treats an unreadable portfolio value as no portfolio, without dropping the row', function (string $total) {
    $ativo = "CNPJ_Fundo_Classe;Data_Referencia;Versao;Total_Investido;Direitos_Bens_Imoveis;CRI;CRI_CRA;LCI;LCI_LCA;Letras_Hipotecarias;LIG;FII\n"
        ."97.521.225/0001-25;2026-09-01;2;{$total};0;;;;;;;\n";
    fakeCvm(cvmZip(null, null, 2026, $ativo));

    $this->artisan('fiis:import-reports')->assertSuccessful();
    $row = FundReportModel::where('ticker', 'MXRF11')->where('month', '2026-09')->first();

    expect($row->total_invested)->toBeNull()->and((float) $row->book_value)->toBe(9.6);
})->with(['texto' => 'abc', 'vazio' => '', 'fora da coluna' => '99999999999999999999999999']);

it('classifies each ticker by its latest report with a portfolio', function () {
    fakeCvm();
    $this->artisan('fiis:import-reports')->assertSuccessful();

    $kinds = app(ReportRepository::class)->kinds();

    expect($kinds)->toBe(['HGLG11' => FundKind::FundOfFunds, 'MXRF11' => FundKind::Paper, 'VISC11' => FundKind::Brick])
        ->and(app(ReportRepository::class)->kindOf('MXRF11'))->toBe(FundKind::Paper)
        ->and(app(ReportRepository::class)->kindOf('KNRI11'))->toBeNull();
});

it('leaves a ticker claimed by two funds without a kind', function () {
    fakeCvm();
    $this->artisan('fiis:import-reports')->assertSuccessful();

    expect(app(ReportRepository::class)->kinds())->not->toHaveKey('XPML11')
        ->and(app(ReportRepository::class)->kindOf('XPML11'))->toBeNull();
});

it('gives the list and the page the same kind when the latest report has total zero', function () {
    FundReportModel::create(['cnpj' => '1', 'ticker' => 'MXRF11', 'month' => '2026-08', 'version' => 1, 'delivered_on' => '2026-09-10', 'total_invested' => '100', 'real_estate' => '90', 'receivables' => '0', 'fii_shares' => '0']);
    FundReportModel::create(['cnpj' => '1', 'ticker' => 'MXRF11', 'month' => '2026-09', 'version' => 1, 'delivered_on' => '2026-10-10', 'total_invested' => '0', 'real_estate' => '0', 'receivables' => '0', 'fii_shares' => '0']);
    $reports = app(ReportRepository::class);

    expect($reports->kinds())->toBe(['MXRF11' => FundKind::Brick])
        ->and($reports->kindOf('MXRF11'))->toBe(FundKind::Brick);
});

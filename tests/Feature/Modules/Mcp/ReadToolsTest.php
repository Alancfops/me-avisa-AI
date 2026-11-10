<?php

use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Ratio;
use App\Modules\Fiis\Infrastructure\Persistence\AlertModel;
use App\Modules\Fiis\Infrastructure\Persistence\DailyPriceModel;
use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundReportModel;
use App\Modules\Fiis\Infrastructure\Persistence\HoldingModel;
use App\Modules\Fiis\Infrastructure\Persistence\QuoteModel;
use App\Modules\Mcp\FiisServer;
use App\Modules\Mcp\Tools\ListFavoritesTool;
use App\Modules\Mcp\Tools\RecentAlertsTool;
use App\Modules\Mcp\Tools\SearchFundsTool;
use App\Modules\Mcp\Tools\ShowFavoriteTool;
use App\Modules\Mcp\Tools\ShowPortfolioTool;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-30 15:00:00-03:00');
    FundModel::create(['ticker' => 'MXRF11', 'name' => 'Maxi Renda']);
    FundModel::create(['ticker' => 'KNRI11', 'name' => 'Kinea Renda Imobiliaria']);
});
afterEach(fn () => Carbon::setTestNow());

function mcpFavoriteWithQuote(): void
{
    FavoriteModel::create(['ticker' => 'MXRF11']);
    QuoteModel::create(['ticker' => 'MXRF11', 'price' => '8.80', 'change_percent' => '-0.5', 'quoted_at' => '2026-10-30T14:30:00-03:00']);
    DailyPriceModel::create(['ticker' => 'MXRF11', 'day' => '2026-10-23', 'close' => '8.00']);
    DailyPriceModel::create(['ticker' => 'MXRF11', 'day' => '2026-09-30', 'close' => '11.00']);
    foreach (range(0, 11) as $back) {
        FundReportModel::create([
            'cnpj' => '11111111000111', 'ticker' => 'MXRF11', 'month' => date('Y-m', strtotime("2026-09-01 -{$back} months")), 'version' => 1,
            'delivered_on' => date('Y-m-d', strtotime("2026-10-10 -{$back} months")), 'book_value' => '10.00', 'dividend_yield' => '0.008',
        ]);
    }
}

function storeAlertAt(string $ticker, string $at): void
{
    AlertModel::create([
        'ticker' => $ticker, 'rule' => 'price_target', 'price' => '8.80', 'reference' => 'alvo R$ 9,00',
        'reason' => 'Preço R$ 8,80 no alvo R$ 9,00', 'occurred_at' => $at, 'quoted_at' => $at,
    ]);
}

it('lists favorites with the price, where it came from and the changes', function () {
    mcpFavoriteWithQuote();
    storeAlertAt('MXRF11', '2026-10-30T10:00:00-03:00');

    FiisServer::tool(ListFavoritesTool::class)
        ->assertOk()
        ->assertSee(['"codigo":"MXRF11"', '"preco":"R$ 8,80"', '"preco_de":"cotação da brapi, 30/10 14:30"', '"variacao_dia":"-0,5%"'])
        ->assertSee(['"variacao_7d":"+10%"', '"variacao_30d":"-20%"', '"pvp":"0,88"', '"alerta":"automático + queda forte"'])
        ->assertSee(['"avisos_hoje":[{"regra":"preço alvo","motivo":"Preço R$ 8,80 no alvo R$ 9,00","horario":"30/10 10:00"}]']);
});

it('gives the same numbers as the site', function () {
    mcpFavoriteWithQuote();
    $site = [];
    $this->get('/')->assertInertia(function ($page) use (&$site) {
        $site = $page->toArray()['props']['favorites'][0];
    });

    $ratio = Ratio::fromString($site['priceToBook']);
    expect($site['dividendYield12m'])->not->toBeNull();
    $yield = number_format((float) $site['dividendYield12m'] * 100, 2, ',', '');

    FiisServer::tool(ListFavoritesTool::class)
        ->assertSee('"preco":"'.Money::fromString($site['price']).'"')
        ->assertSee('"pvp":"'.$ratio.'"')
        ->assertSee('"dy_12m":"'.rtrim(rtrim($yield, '0'), ',').'%"');
});

it('says "sem dado" for a favorite without quote or closes', function () {
    FavoriteModel::create(['ticker' => 'MXRF11']);

    FiisServer::tool(ListFavoritesTool::class)
        ->assertOk()
        ->assertSee(['"preco":"sem dado"', '"preco_de":"sem preço"', '"variacao_7d":"sem dado"', '"pvp":"sem dado"']);
});

it('answers plain text when there are no favorites or no holdings', function () {
    FiisServer::tool(ListFavoritesTool::class)->assertOk()
        ->assertSee('Você ainda não tem favoritos. Dá para favoritar pela conversa, com o código do fundo.');
    FiisServer::tool(ShowPortfolioTool::class)->assertOk()->assertSee('Sua carteira está vazia.');
});

it('shows a favorite with averages, rules and income, accepting a messy code', function () {
    mcpFavoriteWithQuote();

    FiisServer::tool(ShowFavoriteTool::class, ['codigo' => ' mxrf11 '])
        ->assertOk()
        ->assertSee(['"codigo":"MXRF11"', '"medias":{"6m":', '"12m":', '"regras":[{"regra":"automática"', '"atendida":false', '"rendimentos_12m":'])
        ->assertSee('"regra":"queda forte"');
});

it('refuses to show a fund that is not a favorite', function () {
    FiisServer::tool(ShowFavoriteTool::class, ['codigo' => 'KNRI11'])
        ->assertHasErrors(['KNRI11 não é favorito. Favorite primeiro para ver os dados dele.']);
});

it('filters recent alerts by code and respects the amount', function () {
    storeAlertAt('MXRF11', '2026-10-29T10:00:00-03:00');
    storeAlertAt('MXRF11', '2026-10-30T10:00:00-03:00');
    storeAlertAt('KNRI11', '2026-10-30T11:00:00-03:00');

    $both = FiisServer::tool(RecentAlertsTool::class, ['quantidade' => 2])->assertOk();
    $both->assertSee(['"codigo":"KNRI11"', '"codigo":"MXRF11"', '"preco":"R$ 8,80"', '"referencia":"alvo R$ 9,00"', '"horario":"30/10 11:00"']);

    FiisServer::tool(RecentAlertsTool::class, ['codigo' => 'mxrf11'])
        ->assertSee('"codigo":"MXRF11"')
        ->assertDontSee('KNRI11');

    FiisServer::tool(RecentAlertsTool::class, ['codigo' => 'MXRF11', 'quantidade' => 1])
        ->assertSee('30/10 10:00')
        ->assertDontSee('29/10');
});

it('shows the portfolio with the same total as the site', function () {
    mcpFavoriteWithQuote();
    HoldingModel::create(['ticker' => 'MXRF11', 'shares' => 100]);
    HoldingModel::create(['ticker' => 'KNRI11', 'shares' => 5]);
    $site = [];
    $this->get('/carteira')->assertInertia(function ($page) use (&$site) {
        $site = $page->toArray()['props']['totals'];
    });

    FiisServer::tool(ShowPortfolioTool::class)
        ->assertOk()
        ->assertSee(['"codigo":"MXRF11"', '"cotas":100', '"valor":"R$ 880,00"', '"preco_de":"cotação da brapi, 30/10 14:30"'])
        ->assertSee('"totais":{"valor":"'.Money::fromString($site['value']).'"')
        ->assertSee(['"sem_preco":1', '"sem_rendimento":1', 'Estimativa pelo DY e pelo VP informados à CVM. O fundo pode pagar outro valor.']);
});

it('searches the catalog and marks favorites and holdings', function () {
    FavoriteModel::create(['ticker' => 'KNRI11']);
    HoldingModel::create(['ticker' => 'KNRI11', 'shares' => 1]);

    FiisServer::tool(SearchFundsTool::class, ['texto' => 'kinea'])
        ->assertOk()
        ->assertSee(['"codigo":"KNRI11"', '"nome":"Kinea Renda Imobiliaria"', '"favorito":true', '"na_carteira":true']);

    FiisServer::tool(SearchFundsTool::class, ['texto' => 'maxi'])
        ->assertSee(['"codigo":"MXRF11"', '"favorito":false', '"na_carteira":false', '"tipo":"sem dado"']);
});

it('reports when a search finds nothing', function () {
    FiisServer::tool(SearchFundsTool::class, ['texto' => 'zzzz'])->assertOk()->assertSee('Nenhum fundo encontrado');
});

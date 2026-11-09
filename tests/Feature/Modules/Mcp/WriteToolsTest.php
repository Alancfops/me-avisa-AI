<?php

use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Infrastructure\Persistence\AlertModel;
use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundModel;
use App\Modules\Fiis\Infrastructure\Persistence\HoldingModel;
use App\Modules\Fiis\Infrastructure\Persistence\QuoteModel;
use App\Modules\Mcp\FiisServer;
use App\Modules\Mcp\Format;
use App\Modules\Mcp\Tools\AddFavoriteTool;
use App\Modules\Mcp\Tools\ChangeAlertTool;
use App\Modules\Mcp\Tools\ChangeHoldingTool;
use App\Modules\Mcp\Tools\RemoveFavoriteTool;
use App\Modules\Mcp\Tools\RemoveHoldingTool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Carbon::setTestNow('2026-10-30 15:00:00-03:00');
    Http::fake(['*' => Http::response('', 500)]);
    FundModel::create(['ticker' => 'MXRF11', 'name' => 'Maxi Renda']);
});
afterEach(fn () => Carbon::setTestNow());

function mcpSettings(string $ticker = 'MXRF11'): ?string
{
    $settings = app(FavoriteRepository::class)->settings($ticker);

    return $settings === null ? null : Format::settings($settings);
}

function mcpFavoriteAt880(): void
{
    FavoriteModel::create(['ticker' => 'MXRF11']);
    QuoteModel::create(['ticker' => 'MXRF11', 'price' => '8.80', 'change_percent' => '-0.5', 'quoted_at' => '2026-10-30T14:30:00-03:00']);
}

it('favorites a fund, accepting a messy code', function () {
    FiisServer::tool(AddFavoriteTool::class, ['codigo' => ' mxrf11 '])
        ->assertOk()
        ->assertSee(['✅ MXRF11 favoritado.', 'Alerta: automático + queda forte (o padrão; dá para trocar).', 'Cada favorito gasta cerca de 350 requisições da brapi por mês (o plano grátis tem 15 mil).']);

    expect(mcpSettings())->toBe('automático + queda forte');
});

it('says a favorite was already a favorite and changes nothing', function () {
    FavoriteModel::create(['ticker' => 'MXRF11']);

    FiisServer::tool(AddFavoriteTool::class, ['codigo' => 'MXRF11'])
        ->assertOk()->assertSee('MXRF11 já era favorito. Nada foi alterado.');
    expect(FavoriteModel::count())->toBe(1);
});

it('refuses to favorite a fund outside the catalog', function () {
    FiisServer::tool(AddFavoriteTool::class, ['codigo' => 'XPTO11'])
        ->assertHasErrors(['Esse fundo não está no catálogo. Confira o código na B3. Nada foi alterado.']);
    expect(FavoriteModel::count())->toBe(0);
});

it('removes a favorite, tells the old alert and keeps the old alerts', function () {
    mcpFavoriteAt880();
    AlertModel::create(['ticker' => 'MXRF11', 'rule' => 'price_target', 'price' => '8.80', 'reference' => 'x', 'reason' => 'y', 'occurred_at' => '2026-10-30T10:00:00-03:00', 'quoted_at' => '2026-10-30T10:00:00-03:00']);

    FiisServer::tool(RemoveFavoriteTool::class, ['codigo' => 'mxrf11'])
        ->assertOk()
        ->assertSee(['✅ MXRF11 removido dos favoritos.', 'O alerta era: automático + queda forte.', 'Os avisos antigos continuam na aba Avisos.']);

    expect(FavoriteModel::count())->toBe(0)->and(AlertModel::count())->toBe(1);
});

it('refuses to remove what is not a favorite', function () {
    FiisServer::tool(RemoveFavoriteTool::class, ['codigo' => 'MXRF11'])
        ->assertHasErrors(['MXRF11 não é favorito. Nada foi alterado.']);
});

it('saves an alert, showing before and after, and says when it triggers now', function () {
    mcpFavoriteAt880();

    FiisServer::tool(ChangeAlertTool::class, ['codigo' => ' mxrf11 ', 'tipo' => 'target', 'preco_alvo' => '9,50', 'queda_forte' => false])
        ->assertOk()
        ->assertSee([
            '✅ Alerta salvo para MXRF11.',
            'Antes: automático + queda forte',
            'Agora: preço alvo R$ 9,50, sem queda forte',
            'O preço atual (R$ 8,80, cotação da brapi, 30/10 14:30) já atende esse alerta; o aviso deve sair agora ou na próxima atualização.',
        ]);

    expect(mcpSettings())->toBe('preço alvo R$ 9,50, sem queda forte');
});

it('accepts a number as the price and a range', function () {
    mcpFavoriteAt880();

    FiisServer::tool(ChangeAlertTool::class, ['codigo' => 'MXRF11', 'tipo' => 'target', 'preco_alvo' => 9.5, 'queda_forte' => true])->assertOk();
    expect(mcpSettings())->toBe('preço alvo R$ 9,50 + queda forte');

    FiisServer::tool(ChangeAlertTool::class, ['codigo' => 'MXRF11', 'tipo' => 'range', 'faixa_min' => 8, 'faixa_max' => '9.2', 'queda_forte' => true])
        ->assertOk()->assertSee('Agora: faixa de compra R$ 8,00 a R$ 9,20 + queda forte');
    expect(mcpSettings())->toBe('faixa de compra R$ 8,00 a R$ 9,20 + queda forte');
});

it('rejects a thousands separator and saves nothing', function () {
    mcpFavoriteAt880();

    FiisServer::tool(ChangeAlertTool::class, ['codigo' => 'MXRF11', 'tipo' => 'target', 'preco_alvo' => '1.234,50', 'queda_forte' => true])
        ->assertHasErrors(['Use só números, como 9,50. Nada foi alterado.']);
    expect(mcpSettings())->toBe('automático + queda forte');
});

it('requires saying what to do with the sharp drop', function () {
    mcpFavoriteAt880();

    FiisServer::tool(ChangeAlertTool::class, ['codigo' => 'MXRF11', 'tipo' => 'target', 'preco_alvo' => '9'])
        ->assertHasErrors(['Diga se a queda forte fica ligada ou desligada. Nada foi alterado.']);
    expect(mcpSettings())->toBe('automático + queda forte');
});

it('asks for confirmation when the value is far from the price, and only saves once confirmed', function () {
    mcpFavoriteAt880();
    $args = ['codigo' => 'MXRF11', 'tipo' => 'target', 'preco_alvo' => '20', 'queda_forte' => true];

    FiisServer::tool(ChangeAlertTool::class, $args)
        ->assertOk()
        ->assertSee([
            '⚠️ Nada foi alterado.',
            'O alvo R$ 20,00 está 127,27% acima do preço atual de MXRF11 (R$ 8,80, cotação da brapi, 30/10 14:30).',
            'Confirme com a pessoa. Se ela disser que sim, chame de novo com confirmado: true.',
        ]);
    expect(mcpSettings())->toBe('automático + queda forte');

    FiisServer::tool(ChangeAlertTool::class, [...$args, 'confirmado' => true])->assertOk()->assertSee('✅ Alerta salvo para MXRF11.');
    expect(mcpSettings())->toBe('preço alvo R$ 20,00 + queda forte');
});

it('names each range value that is too far', function () {
    mcpFavoriteAt880();

    FiisServer::tool(ChangeAlertTool::class, ['codigo' => 'MXRF11', 'tipo' => 'range', 'faixa_min' => '2', 'faixa_max' => '9', 'queda_forte' => true])
        ->assertSee('O mínimo da faixa R$ 2,00 está 77,27% abaixo do preço atual de MXRF11')
        ->assertDontSee('O máximo da faixa');
});

it('says when the alert was already that one', function () {
    mcpFavoriteAt880();

    FiisServer::tool(ChangeAlertTool::class, ['codigo' => 'MXRF11', 'tipo' => 'automatic', 'queda_forte' => true])
        ->assertOk()->assertSee('O alerta de MXRF11 já era esse: automático + queda forte. Nada foi alterado.');
});

it('warns when there is no price to check the distance', function () {
    FavoriteModel::create(['ticker' => 'MXRF11']);

    FiisServer::tool(ChangeAlertTool::class, ['codigo' => 'MXRF11', 'tipo' => 'target', 'preco_alvo' => '9', 'queda_forte' => true])
        ->assertOk()->assertSee('Sem preço atual para conferir a distância do alvo.');
    expect(mcpSettings())->toBe('preço alvo R$ 9,00 + queda forte');
});

it('refuses to change the alert of a fund that is not a favorite', function () {
    FiisServer::tool(ChangeAlertTool::class, ['codigo' => 'MXRF11', 'tipo' => 'automatic', 'queda_forte' => true])
        ->assertHasErrors(['MXRF11 não é favorito. Nada foi alterado.']);
});

it('sets, adds and removes shares', function () {
    FiisServer::tool(ChangeHoldingTool::class, ['codigo' => ' mxrf11 ', 'variacao' => 100])
        ->assertOk()->assertSee(['✅ Carteira atualizada.', 'MXRF11: entrou com 100 cotas']);

    FiisServer::tool(ChangeHoldingTool::class, ['codigo' => 'MXRF11', 'variacao' => -40])->assertSee('MXRF11: 100 → 60 cotas');
    FiisServer::tool(ChangeHoldingTool::class, ['codigo' => 'MXRF11', 'total' => 80])->assertSee('MXRF11: 60 → 80 cotas');
    expect(HoldingModel::where('ticker', 'MXRF11')->value('shares'))->toBe(80);

    FiisServer::tool(ChangeHoldingTool::class, ['codigo' => 'MXRF11', 'variacao' => -80])->assertSee('MXRF11: 80 → 0 cotas, saiu da carteira.');
    expect(HoldingModel::count())->toBe(0);
});

it('refuses total and variation together', function () {
    FiisServer::tool(ChangeHoldingTool::class, ['codigo' => 'MXRF11', 'total' => 10, 'variacao' => 5])
        ->assertHasErrors(['Informe o total de cotas ou quanto somar ou tirar, não os dois. Nada foi alterado.']);
    expect(HoldingModel::count())->toBe(0);
});

it('removes a fund from the portfolio', function () {
    HoldingModel::create(['ticker' => 'MXRF11', 'shares' => 30]);

    FiisServer::tool(RemoveHoldingTool::class, ['codigo' => 'mxrf11'])
        ->assertOk()->assertSee('✅ MXRF11 saiu da carteira (tinha 30 cotas).');
    expect(HoldingModel::count())->toBe(0);
});

it('refuses to remove a fund that is not in the portfolio', function () {
    FiisServer::tool(RemoveHoldingTool::class, ['codigo' => 'MXRF11'])
        ->assertHasErrors(['MXRF11 não está na carteira. Nada foi alterado.']);
});

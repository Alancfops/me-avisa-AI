<?php

namespace App\Modules\Mcp;

use App\Modules\Mcp\Tools\AddFavoriteTool;
use App\Modules\Mcp\Tools\ChangeAlertTool;
use App\Modules\Mcp\Tools\ChangeHoldingTool;
use App\Modules\Mcp\Tools\ListFavoritesTool;
use App\Modules\Mcp\Tools\RecentAlertsTool;
use App\Modules\Mcp\Tools\RemoveFavoriteTool;
use App\Modules\Mcp\Tools\RemoveHoldingTool;
use App\Modules\Mcp\Tools\SearchFundsTool;
use App\Modules\Mcp\Tools\ShowFavoriteTool;
use App\Modules\Mcp\Tools\ShowPortfolioTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Me Avisa Aí')]
#[Version('1.0.0')]
#[Instructions('Dados dos FIIs favoritos e da carteira de uma pessoa, guardados no app Me Avisa Aí, que roda no computador dela. O app informa e não recomenda investimentos: um aviso diz que uma regra foi atendida, não que o fundo está barato ou deve ser comprado. Ao citar um preço, diga de onde e de quando ele é. Antes de trocar alerta, remover favorito ou mexer na carteira, confira com a pessoa o fundo e os valores. Valores em reais, com vírgula decimal.')]
final class FiisServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        ListFavoritesTool::class,
        ShowFavoriteTool::class,
        RecentAlertsTool::class,
        ShowPortfolioTool::class,
        SearchFundsTool::class,
        AddFavoriteTool::class,
        RemoveFavoriteTool::class,
        ChangeAlertTool::class,
        ChangeHoldingTool::class,
        RemoveHoldingTool::class,
    ];
}

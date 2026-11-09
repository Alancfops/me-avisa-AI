<?php

namespace App\Modules\Mcp\Tools;

use App\Modules\Fiis\Application\SummarizeFavorites;
use App\Modules\Mcp\FavoriteCard;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_favorites')]
#[Description('Lista os FIIs favoritos com preço (e de onde ele vem), variação do dia, de 7 e de 30 dias, P/VP, DY de 12 meses, último rendimento por cota estimado pelo informe da CVM, o alerta configurado e os avisos de hoje. "sem dado" é dado que falta, não zero.')]
#[IsReadOnly]
final class ListFavoritesTool extends Tool
{
    public function handle(Request $request, SummarizeFavorites $summarize): Response
    {
        $favorites = $summarize->all();

        if ($favorites === []) {
            return Response::text('Você ainda não tem favoritos. Dá para favoritar pela conversa, com o código do fundo.');
        }

        return Response::json(['favoritos' => array_map(FavoriteCard::of(...), $favorites)]);
    }
}

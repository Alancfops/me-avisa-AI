<?php

namespace App\Modules\Mcp\Tools;

use App\Modules\Fiis\Application\AddFavorite;
use App\Modules\Fiis\Application\CatalogRepository;
use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Application\InvalidInput;
use App\Modules\Mcp\ToolInput;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('add_favorite')]
#[Description('Favorita um FII do catálogo. O alerta começa automático com queda forte; para outro, use change_alert depois.')]
final class AddFavoriteTool extends Tool
{
    public function handle(Request $request, AddFavorite $add, FavoriteRepository $favorites, CatalogRepository $catalog): Response
    {
        try {
            $ticker = ToolInput::ticker($request);

            if (! $catalog->exists($ticker)) {
                throw new InvalidInput(['ticker' => InvalidInput::NOT_IN_CATALOG]);
            }
        } catch (InvalidInput $invalid) {
            return Response::error($invalid->getMessage().' Nada foi alterado.');
        }

        if ($favorites->exists($ticker)) {
            return Response::text("{$ticker} já era favorito. Nada foi alterado.");
        }

        $add->handle($ticker);

        return Response::text(implode("\n", [
            "✅ {$ticker} favoritado.",
            'Alerta: automático + queda forte (o padrão; dá para trocar).',
            'Cada favorito gasta cerca de 350 requisições da brapi por mês (o plano grátis tem 15 mil).',
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['codigo' => $schema->string()->description('Código do fundo, como MXRF11.')->required()];
    }
}

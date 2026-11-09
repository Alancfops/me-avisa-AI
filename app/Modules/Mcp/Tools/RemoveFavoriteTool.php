<?php

namespace App\Modules\Mcp\Tools;

use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Application\InvalidInput;
use App\Modules\Fiis\Application\RemoveFavorite;
use App\Modules\Mcp\Format;
use App\Modules\Mcp\ToolInput;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('remove_favorite')]
#[Description('Remove um FII dos favoritos. O alerta configurado e o ciclo de avisos dele se perdem; os avisos já emitidos continuam. Confirme o fundo com a pessoa antes.')]
#[IsDestructive]
final class RemoveFavoriteTool extends Tool
{
    public function handle(Request $request, RemoveFavorite $remove, FavoriteRepository $favorites): Response
    {
        try {
            $ticker = ToolInput::ticker($request);
        } catch (InvalidInput $invalid) {
            return Response::error($invalid->getMessage().' Nada foi alterado.');
        }

        $settings = $favorites->settings($ticker);

        if ($settings === null) {
            return Response::error("{$ticker} não é favorito. Nada foi alterado.");
        }

        $remove->handle($ticker);

        return Response::text(implode("\n", [
            "✅ {$ticker} removido dos favoritos.",
            'O alerta era: '.Format::settings($settings).'.',
            'Os avisos antigos continuam na aba Avisos.',
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['codigo' => $schema->string()->description('Código do fundo, como MXRF11.')->required()];
    }
}

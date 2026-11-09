<?php

namespace App\Modules\Mcp\Tools;

use App\Modules\Fiis\Application\CatalogEntry;
use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Application\FundListItem;
use App\Modules\Fiis\Application\HoldingRepository;
use App\Modules\Fiis\Application\ListFunds;
use App\Modules\Fiis\Application\SearchFunds;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_funds')]
#[Description('Procura FIIs no catálogo pelo código ou por parte do nome (até 10) e diz o tipo, o segmento e se já são favoritos ou estão na carteira.')]
#[IsReadOnly]
final class SearchFundsTool extends Tool
{
    private const LIMIT = 10;

    public function handle(Request $request, SearchFunds $search, ListFunds $funds, FavoriteRepository $favorites, HoldingRepository $holdings): Response
    {
        $text = $request->get('texto');
        $entries = is_string($text) ? array_slice($search->handle($text), 0, self::LIMIT) : [];

        if ($entries === []) {
            return Response::text('Nenhum fundo encontrado para essa busca.');
        }

        // Tipo e segmento vêm da lista de fundos, que só tem os que negociaram há pouco.
        $details = [];
        foreach ($funds->handle() as $item) {
            $details[$item->ticker] = $item;
        }

        return Response::json(['fundos' => array_map(fn (CatalogEntry $entry): array => [
            'codigo' => $entry->ticker,
            'nome' => $entry->name,
            'tipo' => self::detail(fn (FundListItem $item): ?string => $item->kind?->value, $details[$entry->ticker] ?? null),
            'segmento' => self::detail(fn (FundListItem $item): ?string => $item->segment, $details[$entry->ticker] ?? null),
            'favorito' => $favorites->exists($entry->ticker),
            'na_carteira' => $holdings->shares($entry->ticker) !== null,
        ], $entries)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['texto' => $schema->string()->description('Código ou parte do nome do fundo.')->required()];
    }

    /** @param  callable(FundListItem): ?string  $pick */
    private static function detail(callable $pick, ?FundListItem $item): string
    {
        return ($item === null ? null : $pick($item)) ?? 'sem dado';
    }
}

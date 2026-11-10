<?php

namespace App\Modules\Mcp\Tools;

use App\Modules\Fiis\Application\InvalidInput;
use App\Modules\Fiis\Application\RuleStatus;
use App\Modules\Fiis\Application\SummarizeFavorites;
use App\Modules\Fiis\Domain\MonthlyDividend;
use App\Modules\Fiis\Domain\WindowAverage;
use App\Modules\Mcp\FavoriteCard;
use App\Modules\Mcp\Format;
use App\Modules\Mcp\ToolInput;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('show_favorite')]
#[Description('Detalha um FII favorito: os dados da lista mais as médias de preço de 6 e 12 meses, a situação de cada regra do alerta agora e os rendimentos por cota dos últimos 12 meses (estimados pelo DY e pelo VP do informe da CVM; o fundo pode pagar outro valor).')]
#[IsReadOnly]
final class ShowFavoriteTool extends Tool
{
    public function handle(Request $request, SummarizeFavorites $summarize): Response
    {
        try {
            $ticker = ToolInput::ticker($request);
        } catch (InvalidInput $invalid) {
            return Response::error($invalid->getMessage());
        }

        $summary = $summarize->one($ticker);

        if ($summary === null) {
            return Response::error("{$ticker} não é favorito. Favorite primeiro para ver os dados dele.");
        }

        $view = $summary->view;
        $average = fn (WindowAverage $window): string => Format::money($window->average);

        return Response::json([
            ...FavoriteCard::of($summary),
            'medias' => ['6m' => $average($view->average6m), '12m' => $average($view->average12m)],
            'regras' => array_map(fn (RuleStatus $status): array => [
                'regra' => Format::rule($status->rule),
                'atendida' => $status->result->matched,
                'motivo' => $status->result->reason ?? $status->result->unavailableReason ?? $status->result->note,
            ], $view->rules),
            'rendimentos_12m' => array_map(fn (MonthlyDividend $dividend): array => [
                'mes' => substr($dividend->month, 5, 2).'/'.substr($dividend->month, 0, 4),
                'por_cota' => Format::money($dividend->perShare),
            ], $view->dividends),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['codigo' => $schema->string()->description('Código do fundo, como MXRF11.')->required()];
    }
}

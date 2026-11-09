<?php

namespace App\Modules\Mcp\Tools;

use App\Modules\Fiis\Application\PortfolioRow;
use App\Modules\Fiis\Application\ShowPortfolio;
use App\Modules\Mcp\Format;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('show_portfolio')]
#[Description('Mostra a carteira: cotas de cada fundo, valor da posição e rendimento estimado (último mês e média) pelo DY e pelo VP do informe da CVM. Fundo sem preço ou sem rendimento fica de fora da soma, e os totais dizem quantos.')]
#[IsReadOnly]
final class ShowPortfolioTool extends Tool
{
    public function handle(Request $request, ShowPortfolio $show): Response
    {
        $view = $show->handle();

        if ($view->rows === []) {
            return Response::text('Sua carteira está vazia.');
        }

        $totals = $view->totals;

        return Response::json([
            'fundos' => array_map(fn (PortfolioRow $row): array => [
                'codigo' => $row->line->ticker,
                'nome' => $row->name,
                'cotas' => $row->line->shares,
                'preco' => Format::money($row->line->price),
                'preco_de' => Format::priceSource($row->quote, $row->close),
                'valor' => Format::money($row->line->positionValue()),
                'rendimento_ultimo_mes' => Format::money($row->line->lastIncome()),
                'rendimento_medio' => Format::money($row->line->averageIncome()),
                'meses_de_rendimento' => $row->line->dividendMonths(),
            ], $view->rows),
            'totais' => [
                'valor' => Format::money($totals->value),
                'rendimento_ultimo_mes' => Format::money($totals->lastIncome),
                'rendimento_medio' => Format::money($totals->averageIncome),
                'sem_preco' => $totals->withoutPrice,
                'sem_rendimento' => $totals->withoutDividends,
            ],
            'observacao' => 'Estimativa pelo DY e pelo VP informados à CVM. O fundo pode pagar outro valor.',
        ]);
    }
}

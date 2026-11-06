<?php

namespace App\Modules\Fiis\Http;

use App\Modules\Fiis\Application\CatalogEntry;
use App\Modules\Fiis\Application\CatalogRepository;
use App\Modules\Fiis\Application\HoldingRepository;
use App\Modules\Fiis\Application\MarketTime;
use App\Modules\Fiis\Application\PortfolioRow;
use App\Modules\Fiis\Application\RemoveHolding;
use App\Modules\Fiis\Application\SaveHolding;
use App\Modules\Fiis\Application\SearchFunds;
use App\Modules\Fiis\Application\ShowPortfolio;
use App\Modules\Fiis\Http\Requests\SaveHoldingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PortfolioController
{
    public function index(
        Request $request,
        ShowPortfolio $show,
        SearchFunds $searchFunds,
        CatalogRepository $catalog,
        HoldingRepository $holdings,
    ): Response {
        $view = $show->handle();
        $query = $request->string('q')->toString();

        return Inertia::render('portfolio', [
            'rows' => array_map(fn (PortfolioRow $row): array => [
                'ticker' => $row->line->ticker,
                'name' => $row->name,
                'shares' => $row->line->shares,
                'price' => $row->line->price?->toDecimal(),
                // O front monta "cotação de HH:MM" ou "fechamento de DD/MM (B3)", como na página do fundo.
                'quotedAt' => $row->quote === null ? null : MarketTime::local($row->quote->quotedAt)->format(DATE_ATOM),
                'closeDay' => $row->close?->date,
                'positionValue' => $row->line->positionValue()?->toDecimal(),
                'lastIncome' => $row->line->lastIncome()?->toDecimal(),
                'averageIncome' => $row->line->averageIncome()?->toDecimal(),
                'dividendMonths' => $row->line->dividendMonths(),
            ], $view->rows),
            'totals' => [
                'value' => $view->totals->value?->toDecimal(),
                'lastIncome' => $view->totals->lastIncome?->toDecimal(),
                'averageIncome' => $view->totals->averageIncome?->toDecimal(),
                'withoutPrice' => $view->totals->withoutPrice,
                'withoutDividends' => $view->totals->withoutDividends,
            ],
            'search' => [
                'query' => $query,
                'catalogEmpty' => $catalog->isEmpty(),
                'results' => array_map(fn (CatalogEntry $entry): array => [
                    'ticker' => $entry->ticker,
                    'name' => $entry->name,
                    'inPortfolio' => $holdings->shares($entry->ticker) !== null,
                ], $searchFunds->handle($query)),
            ],
        ]);
    }

    public function save(SaveHoldingRequest $request, SaveHolding $save): RedirectResponse
    {
        $save->handle($request->string('ticker')->toString(), $request->integer('shares'));

        return $request->boolean('stay') ? redirect()->back() : redirect()->route('portfolio');
    }

    public function remove(string $ticker, RemoveHolding $remove): RedirectResponse
    {
        abort_unless($remove->handle(strtoupper($ticker)), 404);

        return redirect()->route('portfolio');
    }
}

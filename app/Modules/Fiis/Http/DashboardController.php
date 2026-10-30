<?php

namespace App\Modules\Fiis\Http;

use App\Modules\Fiis\Application\CatalogEntry;
use App\Modules\Fiis\Application\CatalogRepository;
use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Application\FavoriteView;
use App\Modules\Fiis\Application\ListFavorites;
use App\Modules\Fiis\Application\MarketTime;
use App\Modules\Fiis\Application\SearchFunds;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    public function __invoke(
        Request $request,
        ListFavorites $listFavorites,
        SearchFunds $searchFunds,
        CatalogRepository $catalog,
        FavoriteRepository $favorites,
    ): Response {
        $query = $request->string('q')->toString();

        return Inertia::render('dashboard', [
            'favorites' => array_map($this->favoriteProps(...), $listFavorites->handle()),
            'search' => [
                'query' => $query,
                'catalogEmpty' => $catalog->isEmpty(),
                'results' => array_map(fn (CatalogEntry $entry): array => [
                    'ticker' => $entry->ticker,
                    'name' => $entry->name,
                    'favorite' => $favorites->exists($entry->ticker),
                ], $searchFunds->handle($query)),
            ],
        ]);
    }

    /** @return array<string, string|null> */
    private function favoriteProps(FavoriteView $favorite): array
    {
        return [
            'ticker' => $favorite->ticker,
            'name' => $favorite->name,
            'price' => $favorite->quote?->price->toDecimal(),
            'changePercent' => $favorite->quote?->changePercent?->toDecimal(),
            'quotedAt' => $favorite->quote === null ? null : MarketTime::local($favorite->quote->quotedAt)->format(DATE_ATOM),
        ];
    }
}

<?php

namespace App\Modules\Fiis\Http;

use App\Modules\Fiis\Application\CatalogEntry;
use App\Modules\Fiis\Application\CatalogRepository;
use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Application\ListFavorites;
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
            'favorites' => array_map(FundController::fundProps(...), $listFavorites->handle()),
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
}

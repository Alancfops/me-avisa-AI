<?php

namespace App\Modules\Fiis\Http;

use App\Modules\Fiis\Application\FavoriteView;
use App\Modules\Fiis\Application\ListFavorites;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    public function __invoke(ListFavorites $listFavorites): Response
    {
        return Inertia::render('dashboard', [
            'favorites' => array_map(
                fn (FavoriteView $favorite): array => ['ticker' => $favorite->ticker],
                $listFavorites->handle(),
            ),
        ]);
    }
}

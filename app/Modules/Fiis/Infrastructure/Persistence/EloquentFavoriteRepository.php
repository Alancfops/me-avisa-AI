<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Application\FavoriteView;

final class EloquentFavoriteRepository implements FavoriteRepository
{
    public function all(): array
    {
        $tickers = FavoriteModel::query()->orderBy('ticker')->pluck('ticker')->all();

        return array_map(
            fn (string $ticker): FavoriteView => new FavoriteView($ticker),
            array_values($tickers),
        );
    }
}

<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use App\Modules\Fiis\Application\FavoriteRepository;

final class EloquentFavoriteRepository implements FavoriteRepository
{
    public function tickers(): array
    {
        return array_values(FavoriteModel::query()->orderBy('ticker')->pluck('ticker')->all());
    }

    public function exists(string $ticker): bool
    {
        return FavoriteModel::query()->where('ticker', $ticker)->exists();
    }

    public function add(string $ticker): void
    {
        FavoriteModel::query()->firstOrCreate(['ticker' => $ticker]);
    }

    public function remove(string $ticker): void
    {
        FavoriteModel::query()->where('ticker', $ticker)->delete();
    }
}

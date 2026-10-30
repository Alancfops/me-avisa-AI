<?php

namespace App\Modules\Fiis;

use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Infrastructure\Persistence\EloquentFavoriteRepository;
use Illuminate\Support\ServiceProvider;

final class FiisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FavoriteRepository::class, EloquentFavoriteRepository::class);
    }
}

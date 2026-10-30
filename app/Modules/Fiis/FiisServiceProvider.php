<?php

namespace App\Modules\Fiis;

use App\Modules\Fiis\Application\CatalogRepository;
use App\Modules\Fiis\Application\Clock;
use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Application\FundCatalog;
use App\Modules\Fiis\Application\MarketData;
use App\Modules\Fiis\Application\Quotes;
use App\Modules\Fiis\Infrastructure\Brapi\BrapiFundCatalog;
use App\Modules\Fiis\Infrastructure\Brapi\BrapiQuotes;
use App\Modules\Fiis\Infrastructure\Console\RefreshQuotesCommand;
use App\Modules\Fiis\Infrastructure\Console\SyncCatalogCommand;
use App\Modules\Fiis\Infrastructure\Persistence\EloquentCatalogRepository;
use App\Modules\Fiis\Infrastructure\Persistence\EloquentFavoriteRepository;
use App\Modules\Fiis\Infrastructure\Persistence\EloquentMarketData;
use App\Modules\Fiis\Infrastructure\SystemClock;
use Illuminate\Support\ServiceProvider;

final class FiisServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CatalogRepository::class => EloquentCatalogRepository::class,
        Clock::class => SystemClock::class,
        FavoriteRepository::class => EloquentFavoriteRepository::class,
        FundCatalog::class => BrapiFundCatalog::class,
        MarketData::class => EloquentMarketData::class,
        Quotes::class => BrapiQuotes::class,
    ];

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RefreshQuotesCommand::class,
                SyncCatalogCommand::class,
            ]);
        }
    }
}

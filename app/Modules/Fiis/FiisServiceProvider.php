<?php

namespace App\Modules\Fiis;

use App\Modules\Fiis\Application\AlertRepository;
use App\Modules\Fiis\Application\CatalogRepository;
use App\Modules\Fiis\Application\Clock;
use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Application\FundCatalog;
use App\Modules\Fiis\Application\FundReports;
use App\Modules\Fiis\Application\MarketData;
use App\Modules\Fiis\Application\QuotaReader;
use App\Modules\Fiis\Application\Quotes;
use App\Modules\Fiis\Application\ReportRepository;
use App\Modules\Fiis\Application\SourceLog;
use App\Modules\Fiis\Infrastructure\Brapi\BrapiFundCatalog;
use App\Modules\Fiis\Infrastructure\Brapi\BrapiQuotaReader;
use App\Modules\Fiis\Infrastructure\Brapi\BrapiQuotes;
use App\Modules\Fiis\Infrastructure\Console\BacktestCommand;
use App\Modules\Fiis\Infrastructure\Console\ImportReportsCommand;
use App\Modules\Fiis\Infrastructure\Console\RecordClosesCommand;
use App\Modules\Fiis\Infrastructure\Console\RefreshQuotesCommand;
use App\Modules\Fiis\Infrastructure\Console\SyncCatalogCommand;
use App\Modules\Fiis\Infrastructure\Cvm\CvmFundReports;
use App\Modules\Fiis\Infrastructure\Persistence\EloquentAlertRepository;
use App\Modules\Fiis\Infrastructure\Persistence\EloquentCatalogRepository;
use App\Modules\Fiis\Infrastructure\Persistence\EloquentFavoriteRepository;
use App\Modules\Fiis\Infrastructure\Persistence\EloquentMarketData;
use App\Modules\Fiis\Infrastructure\Persistence\EloquentReportRepository;
use App\Modules\Fiis\Infrastructure\Persistence\EloquentSourceLog;
use App\Modules\Fiis\Infrastructure\SystemClock;
use Illuminate\Support\ServiceProvider;

final class FiisServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        AlertRepository::class => EloquentAlertRepository::class,
        CatalogRepository::class => EloquentCatalogRepository::class,
        Clock::class => SystemClock::class,
        FavoriteRepository::class => EloquentFavoriteRepository::class,
        FundCatalog::class => BrapiFundCatalog::class,
        FundReports::class => CvmFundReports::class,
        MarketData::class => EloquentMarketData::class,
        QuotaReader::class => BrapiQuotaReader::class,
        Quotes::class => BrapiQuotes::class,
        ReportRepository::class => EloquentReportRepository::class,
        SourceLog::class => EloquentSourceLog::class,
    ];

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BacktestCommand::class,
                ImportReportsCommand::class,
                RecordClosesCommand::class,
                RefreshQuotesCommand::class,
                SyncCatalogCommand::class,
            ]);
        }
    }
}

<?php

namespace App\Modules\Fiis\Infrastructure\Console;

use App\Modules\Fiis\Application\SourceUnavailable;
use App\Modules\Fiis\Application\SyncCatalog;
use Illuminate\Console\Command;

final class SyncCatalogCommand extends Command
{
    protected $signature = 'fiis:sync-catalog';

    protected $description = 'Atualiza o catálogo local de FIIs usado na busca';

    public function handle(SyncCatalog $syncCatalog): int
    {
        try {
            $this->info("Catálogo atualizado: {$syncCatalog->handle()} fundos.");

            return self::SUCCESS;
        } catch (SourceUnavailable $failure) {
            $this->error($failure->getMessage());

            return self::FAILURE;
        }
    }
}

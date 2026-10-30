<?php

namespace App\Modules\Fiis\Infrastructure\Console;

use App\Modules\Fiis\Application\CatchUp;
use Illuminate\Console\Command;

final class CatchUpCommand extends Command
{
    protected $signature = 'fiis:catch-up';

    protected $description = 'Recupera fechamentos e informes perdidos com a máquina desligada';

    public function handle(CatchUp $catchUp): int
    {
        $catchUp->handle();
        $this->info('Histórico conferido.');

        return self::SUCCESS;
    }
}

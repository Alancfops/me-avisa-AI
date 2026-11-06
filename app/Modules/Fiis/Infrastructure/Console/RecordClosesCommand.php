<?php

namespace App\Modules\Fiis\Infrastructure\Console;

use App\Modules\Fiis\Application\RecordDailyCloses;
use Illuminate\Console\Command;

final class RecordClosesCommand extends Command
{
    protected $signature = 'fiis:record-closes';

    protected $description = 'Grava os fechamentos dos favoritos no histórico';

    public function handle(RecordDailyCloses $recordDailyCloses): int
    {
        $recordDailyCloses->handle();
        $this->info('Fechamentos gravados.');

        return self::SUCCESS;
    }
}

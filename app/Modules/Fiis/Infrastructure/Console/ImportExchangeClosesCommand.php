<?php

namespace App\Modules\Fiis\Infrastructure\Console;

use App\Modules\Fiis\Application\ImportExchangeCloses;
use Illuminate\Console\Command;

final class ImportExchangeClosesCommand extends Command
{
    protected $signature = 'fiis:import-exchange-closes';

    protected $description = 'Importa os fechamentos dos FIIs do catálogo pelos arquivos diários da B3 (últimos 10 dias úteis)';

    public function handle(ImportExchangeCloses $import): int
    {
        // Falha de um dia vai para a Saúde e o dia é tentado de novo na rodada seguinte.
        $import->handle();

        $this->info('Fechamentos da B3 importados.');

        return self::SUCCESS;
    }
}

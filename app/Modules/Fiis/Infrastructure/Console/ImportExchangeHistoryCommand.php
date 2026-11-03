<?php

namespace App\Modules\Fiis\Infrastructure\Console;

use App\Modules\Fiis\Application\ImportExchangeHistory;
use Illuminate\Console\Command;

final class ImportExchangeHistoryCommand extends Command
{
    protected $signature = 'fiis:import-exchange-history {--force : Recarrega os dois anos mesmo já carregados}';

    protected $description = 'Carrega 12 meses de fechamentos dos FIIs do catálogo pelos arquivos anuais da B3 (cada ano é carregado uma vez; o que falhou é tentado de novo)';

    public function handle(ImportExchangeHistory $import): int
    {
        // Falha vai para a Saúde e a próxima rodada tenta de novo.
        $import->handle((bool) $this->option('force'));

        $this->info('Histórico da B3 conferido.');

        return self::SUCCESS;
    }
}

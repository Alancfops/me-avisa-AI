<?php

namespace App\Modules\Fiis\Infrastructure\Console;

use App\Modules\Fiis\Application\ImportReports;
use App\Modules\Fiis\Application\SourceUnavailable;
use Illuminate\Console\Command;

final class ImportReportsCommand extends Command
{
    protected $signature = 'fiis:import-reports';

    protected $description = 'Importa VP e DY mensais do Informe da CVM (ano corrente e anterior)';

    public function handle(ImportReports $importReports): int
    {
        try {
            $result = $importReports->handle();
        } catch (SourceUnavailable $failure) {
            $this->error($failure->getMessage());

            return self::FAILURE;
        }

        $this->info("Informes importados: {$result->imported}.");

        if ($result->ambiguousTickers !== []) {
            $this->warn('Sem VP, porque mais de um fundo declara o mesmo ISIN: '.implode(', ', $result->ambiguousTickers).'.');
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Modules\Fiis\Infrastructure\Console;

use App\Modules\Fiis\Application\EvaluateFavorites;
use App\Modules\Fiis\Application\RefreshQuotes;
use Illuminate\Console\Command;

final class RefreshQuotesCommand extends Command
{
    protected $signature = 'fiis:refresh-quotes';

    protected $description = 'Atualiza a cotação de cada favorito e avalia as regras';

    public function handle(RefreshQuotes $refreshQuotes, EvaluateFavorites $evaluateFavorites): int
    {
        $result = $refreshQuotes->handle();
        $evaluateFavorites->handle();

        $this->info('Atualizados: '.count($result->updated).'. Com falha: '.count($result->failed).'.');

        return self::SUCCESS;
    }
}

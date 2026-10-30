<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use App\Modules\Fiis\Application\ReportRepository;

/** Até os informes da CVM entrarem, nenhum fundo tem VP, e a tela diz isso. */
final class NoReports implements ReportRepository
{
    public function bookValues(string $ticker): array
    {
        return [];
    }
}

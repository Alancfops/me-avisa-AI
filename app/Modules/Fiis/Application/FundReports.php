<?php

namespace App\Modules\Fiis\Application;

interface FundReports
{
    /**
     * Uma linha por fundo e competência: a de maior versão.
     *
     * @return list<FundReportRecord>
     *
     * @throws SourceUnavailable
     */
    public function forYear(int $year): array;
}

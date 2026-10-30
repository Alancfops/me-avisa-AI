<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\BookValueReport;

interface ReportRepository
{
    /** @return list<BookValueReport> */
    public function bookValues(string $ticker): array;
}

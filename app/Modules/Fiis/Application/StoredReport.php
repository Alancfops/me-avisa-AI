<?php

namespace App\Modules\Fiis\Application;

final readonly class StoredReport
{
    public function __construct(
        public string $ticker,
        public FundReportRecord $record,
    ) {}
}

<?php

namespace App\Modules\Fiis\Application;

final readonly class FundReportRecord
{
    public function __construct(
        public string $cnpj,
        public string $isin,
        /** Competência em `Y-m`. */
        public string $month,
        public int $version,
        public string $deliveredOn,
        public ?string $bookValue,
        public ?string $dividendYield,
    ) {}
}

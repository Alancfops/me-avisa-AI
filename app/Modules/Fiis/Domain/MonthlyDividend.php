<?php

namespace App\Modules\Fiis\Domain;

final readonly class MonthlyDividend
{
    public function __construct(
        /** Competência em `Y-m`. */
        public string $month,
        public Money $perShare,
    ) {}
}

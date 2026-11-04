<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\PortfolioTotals;

final readonly class PortfolioView
{
    /** @param list<PortfolioRow> $rows maior posição primeiro; sem preço no fim, por ticker */
    public function __construct(
        public array $rows,
        public PortfolioTotals $totals,
    ) {}
}

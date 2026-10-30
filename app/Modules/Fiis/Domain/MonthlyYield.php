<?php

namespace App\Modules\Fiis\Domain;

final readonly class MonthlyYield
{
    public function __construct(
        public string $month,
        public string $deliveredOn,
        /** Fração, como a CVM grava: `0.004342` é 0,43%. */
        public string $fraction,
    ) {}
}

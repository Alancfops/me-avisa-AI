<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\Money;

final readonly class DailyClose
{
    public function __construct(
        /** Pregão em `Y-m-d`, no fuso de São Paulo. */
        public string $date,
        public Money $close,
    ) {}
}

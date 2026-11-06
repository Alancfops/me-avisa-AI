<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\DailyClose;

final readonly class ExchangeClose
{
    public function __construct(
        public string $ticker,
        public DailyClose $close,
    ) {}
}

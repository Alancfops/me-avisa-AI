<?php

namespace Tests\Support\Fiis;

use App\Modules\Fiis\Application\ExchangeImports;

final class InMemoryExchangeImports implements ExchangeImports
{
    /** @var array<string, true> */
    private array $days = [];

    public function has(string $day): bool
    {
        return isset($this->days[$day]);
    }

    public function mark(string $day): void
    {
        $this->days[$day] = true;
    }
}

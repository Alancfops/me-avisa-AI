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

    public function latestDay(): ?string
    {
        return $this->days === [] ? null : max(array_keys($this->days));
    }

    /** @var array<int, true> */
    private array $years = [];

    public function hasYear(int $year): bool
    {
        return isset($this->years[$year]);
    }

    public function markYear(int $year): void
    {
        $this->years[$year] = true;
    }
}

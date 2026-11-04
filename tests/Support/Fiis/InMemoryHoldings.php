<?php

namespace Tests\Support\Fiis;

use App\Modules\Fiis\Application\HoldingRepository;

final class InMemoryHoldings implements HoldingRepository
{
    /** @param array<string, int> $holdings */
    public function __construct(public array $holdings = []) {}

    public function all(): array
    {
        $holdings = $this->holdings;
        ksort($holdings);

        return $holdings;
    }

    public function shares(string $ticker): ?int
    {
        return $this->holdings[$ticker] ?? null;
    }

    public function save(string $ticker, int $shares): void
    {
        $this->holdings[$ticker] = $shares;
    }

    public function remove(string $ticker): bool
    {
        $existed = isset($this->holdings[$ticker]);
        unset($this->holdings[$ticker]);

        return $existed;
    }
}

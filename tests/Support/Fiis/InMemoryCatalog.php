<?php

namespace Tests\Support\Fiis;

use App\Modules\Fiis\Application\CatalogRepository;

final class InMemoryCatalog implements CatalogRepository
{
    /** @param array<string, string> $names */
    public function __construct(public array $names = []) {}

    public function save(array $entries): void {}

    public function search(string $query): array
    {
        return [];
    }

    public function exists(string $ticker): bool
    {
        return isset($this->names[$ticker]);
    }

    public function nameOf(string $ticker): ?string
    {
        return $this->names[$ticker] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->names === [];
    }
}

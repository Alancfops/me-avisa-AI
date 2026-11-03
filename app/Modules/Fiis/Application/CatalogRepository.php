<?php

namespace App\Modules\Fiis\Application;

interface CatalogRepository
{
    /** @param list<CatalogEntry> $entries */
    public function save(array $entries): void;

    /** @return list<CatalogEntry> */
    public function search(string $query): array;

    /** @return list<CatalogEntry> em ordem de ticker */
    public function all(): array;

    /** @return list<string> */
    public function tickers(): array;

    public function exists(string $ticker): bool;

    public function nameOf(string $ticker): ?string;

    public function isEmpty(): bool;
}

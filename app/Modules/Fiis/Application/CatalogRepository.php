<?php

namespace App\Modules\Fiis\Application;

interface CatalogRepository
{
    /** @param list<CatalogEntry> $entries */
    public function save(array $entries): void;

    /** @return list<CatalogEntry> */
    public function search(string $query): array;

    public function exists(string $ticker): bool;

    public function nameOf(string $ticker): ?string;

    public function isEmpty(): bool;
}

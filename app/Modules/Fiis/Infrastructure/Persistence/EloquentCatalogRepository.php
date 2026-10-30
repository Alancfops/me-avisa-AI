<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use App\Modules\Fiis\Application\CatalogEntry;
use App\Modules\Fiis\Application\CatalogRepository;

final class EloquentCatalogRepository implements CatalogRepository
{
    private const SEARCH_LIMIT = 20;

    public function save(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        FundModel::query()->upsert(
            array_map(fn (CatalogEntry $entry): array => ['ticker' => $entry->ticker, 'name' => $entry->name], $entries),
            ['ticker'],
            ['name'],
        );
    }

    public function search(string $query): array
    {
        $pattern = '%'.addcslashes($query, '%_\\').'%';

        return array_values(FundModel::query()
            ->where(fn ($where) => $where->where('ticker', 'ilike', $pattern)->orWhere('name', 'ilike', $pattern))
            ->orderBy('ticker')
            ->limit(self::SEARCH_LIMIT)
            ->get()
            ->map(fn (FundModel $fund): CatalogEntry => new CatalogEntry((string) $fund->getAttribute('ticker'), (string) $fund->getAttribute('name')))
            ->all());
    }

    public function exists(string $ticker): bool
    {
        return FundModel::query()->where('ticker', $ticker)->exists();
    }

    public function nameOf(string $ticker): ?string
    {
        $name = FundModel::query()->where('ticker', $ticker)->value('name');

        return is_string($name) ? $name : null;
    }

    public function isEmpty(): bool
    {
        return ! FundModel::query()->exists();
    }
}

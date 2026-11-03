<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use App\Modules\Fiis\Application\ExchangeImports;

final class EloquentExchangeImports implements ExchangeImports
{
    public function has(string $day): bool
    {
        return ExchangeImportModel::query()->whereKey($day)->exists();
    }

    public function mark(string $day): void
    {
        ExchangeImportModel::query()->updateOrCreate(['day' => $day]);
    }

    public function latestDay(): ?string
    {
        $day = ExchangeImportModel::query()->max('day');

        return $day === null ? null : substr((string) $day, 0, 10);
    }

    public function hasYear(int $year): bool
    {
        return ExchangeYearImportModel::query()->whereKey($year)->exists();
    }

    public function markYear(int $year): void
    {
        ExchangeYearImportModel::query()->updateOrCreate(['year' => $year]);
    }
}

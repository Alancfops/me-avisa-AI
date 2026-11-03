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

    public function hasYear(int $year): bool
    {
        return ExchangeYearImportModel::query()->whereKey($year)->exists();
    }

    public function markYear(int $year): void
    {
        ExchangeYearImportModel::query()->updateOrCreate(['year' => $year]);
    }
}

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
}

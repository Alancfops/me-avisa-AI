<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use App\Modules\Fiis\Application\HoldingRepository;

final class EloquentHoldingRepository implements HoldingRepository
{
    public function all(): array
    {
        $all = [];

        foreach (HoldingModel::query()->orderBy('ticker')->get() as $row) {
            $all[$row->ticker] = $row->shares;
        }

        return $all;
    }

    public function shares(string $ticker): ?int
    {
        return HoldingModel::query()->where('ticker', $ticker)->first()?->shares;
    }

    public function save(string $ticker, int $shares): void
    {
        HoldingModel::query()->updateOrCreate(['ticker' => $ticker], ['shares' => $shares]);
    }

    public function remove(string $ticker): bool
    {
        return HoldingModel::query()->where('ticker', $ticker)->delete() > 0;
    }
}

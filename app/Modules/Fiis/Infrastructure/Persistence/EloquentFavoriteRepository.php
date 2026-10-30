<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Domain\AlertMode;
use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\Money;
use DateTimeImmutable;

final class EloquentFavoriteRepository implements FavoriteRepository
{
    public function tickers(): array
    {
        return array_values(FavoriteModel::query()->orderBy('ticker')->pluck('ticker')->all());
    }

    public function exists(string $ticker): bool
    {
        return FavoriteModel::query()->where('ticker', $ticker)->exists();
    }

    public function add(string $ticker): void
    {
        FavoriteModel::query()->firstOrCreate(['ticker' => $ticker]);
    }

    public function remove(string $ticker): void
    {
        FavoriteModel::query()->where('ticker', $ticker)->delete();
    }

    public function settings(string $ticker): ?FavoriteSettings
    {
        $row = FavoriteModel::query()->where('ticker', $ticker)->first();

        if ($row === null) {
            return null;
        }

        return match (AlertMode::from($row->mode)) {
            AlertMode::Automatic => FavoriteSettings::automatic($row->sharp_drop),
            AlertMode::Target => FavoriteSettings::target(Money::fromString((string) $row->target), $row->sharp_drop),
            AlertMode::Range => FavoriteSettings::range(Money::fromString((string) $row->range_min), Money::fromString((string) $row->range_max), $row->sharp_drop),
        };
    }

    public function saveSettings(string $ticker, FavoriteSettings $settings): void
    {
        FavoriteModel::query()->where('ticker', $ticker)->update([
            'mode' => $settings->mode->value,
            'target' => $settings->target?->toDecimal(),
            'range_min' => $settings->rangeMin?->toDecimal(),
            'range_max' => $settings->rangeMax?->toDecimal(),
            'sharp_drop' => $settings->sharpDrop,
        ]);
    }

    public function lastEvaluatedQuoteAt(string $ticker): ?DateTimeImmutable
    {
        return FavoriteModel::query()->where('ticker', $ticker)->first()?->last_evaluated_quote_at?->toDateTimeImmutable();
    }

    public function markEvaluated(string $ticker, DateTimeImmutable $quotedAt): void
    {
        FavoriteModel::query()->where('ticker', $ticker)->update(['last_evaluated_quote_at' => $quotedAt->format(DATE_ATOM)]);
    }

    public function historyCheckedOn(string $ticker): ?string
    {
        $day = FavoriteModel::query()->where('ticker', $ticker)->value('history_checked_on');

        return is_string($day) ? substr($day, 0, 10) : null;
    }

    public function markHistoryChecked(string $ticker, string $day): void
    {
        FavoriteModel::query()->where('ticker', $ticker)->update(['history_checked_on' => $day]);
    }
}

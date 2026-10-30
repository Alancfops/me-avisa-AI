<?php

namespace Tests\Support\Fiis;

use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Domain\FavoriteSettings;
use DateTimeImmutable;

final class InMemoryFavorites implements FavoriteRepository
{
    /** @var array<string, FavoriteSettings> */
    public array $favorites = [];

    /** @var array<string, DateTimeImmutable> */
    public array $evaluated = [];

    /** @param list<string> $tickers */
    public function __construct(array $tickers = [])
    {
        foreach ($tickers as $ticker) {
            $this->add($ticker);
        }
    }

    public function tickers(): array
    {
        $tickers = array_keys($this->favorites);
        sort($tickers);

        return $tickers;
    }

    public function exists(string $ticker): bool
    {
        return isset($this->favorites[$ticker]);
    }

    public function add(string $ticker): void
    {
        $this->favorites[$ticker] ??= FavoriteSettings::default();
    }

    public function remove(string $ticker): void
    {
        unset($this->favorites[$ticker]);
    }

    public function settings(string $ticker): ?FavoriteSettings
    {
        return $this->favorites[$ticker] ?? null;
    }

    public function saveSettings(string $ticker, FavoriteSettings $settings): void
    {
        $this->favorites[$ticker] = $settings;
    }

    public function lastEvaluatedQuoteAt(string $ticker): ?DateTimeImmutable
    {
        return $this->evaluated[$ticker] ?? null;
    }

    public function markEvaluated(string $ticker, DateTimeImmutable $quotedAt): void
    {
        $this->evaluated[$ticker] = $quotedAt;
    }
}

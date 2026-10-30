<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\FavoriteSettings;
use DateTimeImmutable;

interface FavoriteRepository
{
    /** @return list<string> em ordem alfabética */
    public function tickers(): array;

    public function exists(string $ticker): bool;

    public function add(string $ticker): void;

    public function remove(string $ticker): void;

    public function settings(string $ticker): ?FavoriteSettings;

    public function saveSettings(string $ticker, FavoriteSettings $settings): void;

    public function lastEvaluatedQuoteAt(string $ticker): ?DateTimeImmutable;

    public function markEvaluated(string $ticker, DateTimeImmutable $quotedAt): void;
}

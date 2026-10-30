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

    /** Último dia em que o histórico foi buscado de novo por estar atrasado. */
    public function historyCheckedOn(string $ticker): ?string;

    public function markHistoryChecked(string $ticker, string $day): void;
}

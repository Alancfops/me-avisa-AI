<?php

namespace App\Modules\Fiis\Application;

interface FavoriteRepository
{
    /** @return list<string> em ordem alfabética */
    public function tickers(): array;

    public function exists(string $ticker): bool;

    public function add(string $ticker): void;

    public function remove(string $ticker): void;
}

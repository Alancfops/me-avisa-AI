<?php

namespace Tests\Support\Fiis;

use App\Modules\Fiis\Application\FavoriteRepository;

final class InMemoryFavorites implements FavoriteRepository
{
    /** @var array<string, true> */
    public array $favorites = [];

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
        $this->favorites[$ticker] = true;
    }

    public function remove(string $ticker): void
    {
        unset($this->favorites[$ticker]);
    }
}

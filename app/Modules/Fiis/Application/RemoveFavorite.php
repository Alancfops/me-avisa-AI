<?php

namespace App\Modules\Fiis\Application;

final readonly class RemoveFavorite
{
    public function __construct(private FavoriteRepository $favorites) {}

    public function handle(string $ticker): void
    {
        $this->favorites->remove($ticker);
    }
}

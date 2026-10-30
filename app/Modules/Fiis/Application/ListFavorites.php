<?php

namespace App\Modules\Fiis\Application;

final readonly class ListFavorites
{
    public function __construct(private FavoriteRepository $favorites) {}

    /** @return list<FavoriteView> */
    public function handle(): array
    {
        return $this->favorites->all();
    }
}

<?php

namespace App\Modules\Fiis\Application;

interface FavoriteRepository
{
    /** @return list<FavoriteView> */
    public function all(): array;
}

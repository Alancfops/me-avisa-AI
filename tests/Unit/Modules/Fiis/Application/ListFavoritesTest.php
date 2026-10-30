<?php

use App\Modules\Fiis\Application\FavoriteRepository;
use App\Modules\Fiis\Application\FavoriteView;
use App\Modules\Fiis\Application\ListFavorites;

it('returns the favorites from the repository', function () {
    $repository = new class implements FavoriteRepository
    {
        public function all(): array
        {
            return [new FavoriteView('MXRF11')];
        }
    };

    expect((new ListFavorites($repository))->handle())->toEqual([new FavoriteView('MXRF11')]);
});

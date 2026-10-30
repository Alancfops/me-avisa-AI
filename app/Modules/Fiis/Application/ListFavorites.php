<?php

namespace App\Modules\Fiis\Application;

final readonly class ListFavorites
{
    public function __construct(
        private FavoriteRepository $favorites,
        private DescribeFund $describe,
    ) {}

    /** @return list<FavoriteView> */
    public function handle(): array
    {
        $views = [];

        foreach ($this->favorites->tickers() as $ticker) {
            $view = $this->describe->handle($ticker);

            if ($view !== null) {
                $views[] = $view;
            }
        }

        return $views;
    }
}

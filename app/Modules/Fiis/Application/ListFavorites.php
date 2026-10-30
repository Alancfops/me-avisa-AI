<?php

namespace App\Modules\Fiis\Application;

final readonly class ListFavorites
{
    public function __construct(
        private FavoriteRepository $favorites,
        private CatalogRepository $catalog,
        private MarketData $market,
    ) {}

    /** @return list<FavoriteView> */
    public function handle(): array
    {
        return array_map(
            fn (string $ticker): FavoriteView => new FavoriteView(
                $ticker,
                $this->catalog->nameOf($ticker),
                $this->market->latestQuote($ticker),
            ),
            $this->favorites->tickers(),
        );
    }
}

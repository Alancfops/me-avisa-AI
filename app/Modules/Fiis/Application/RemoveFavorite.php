<?php

namespace App\Modules\Fiis\Application;

final readonly class RemoveFavorite
{
    public function __construct(
        private FavoriteRepository $favorites,
        private AlertRepository $alerts,
        private MarketData $market,
    ) {}

    /**
     * Os avisos e os fechamentos ficam; acabam o ciclo do antispam e a cotação, que ao
     * favoritar de novo seria avaliada como novidade e dispararia com um preço velho.
     */
    public function handle(string $ticker): void
    {
        $this->favorites->remove($ticker);
        $this->alerts->resetAllStates($ticker);
        $this->market->deleteQuote($ticker);
    }
}

<?php

namespace App\Modules\Fiis\Application;

final readonly class RemoveFavorite
{
    public function __construct(
        private FavoriteRepository $favorites,
        private AlertRepository $alerts,
    ) {}

    /** Os avisos já dados ficam no histórico; só o ciclo do antispam acaba. */
    public function handle(string $ticker): void
    {
        $this->favorites->remove($ticker);
        $this->alerts->resetAllStates($ticker);
    }
}

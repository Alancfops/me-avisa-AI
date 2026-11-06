<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\FavoriteSettings;

final readonly class UpdateFavoriteSettings
{
    public function __construct(
        private FavoriteRepository $favorites,
        private AlertRepository $alerts,
        private EvaluateFavorites $evaluate,
    ) {}

    public function handle(string $ticker, FavoriteSettings $settings): void
    {
        $current = $this->favorites->settings($ticker);

        if ($current === null) {
            return;
        }

        $changed = $current->changedRules($settings);

        if ($changed === []) {
            return;
        }

        $this->favorites->saveSettings($ticker, $settings);
        $this->alerts->resetStates($ticker, $changed);
        $this->evaluate->handleNow($ticker);
    }
}

<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\FundSnapshot;

final readonly class FavoriteView
{
    /** @param list<RuleStatus> $rules situação agora; vazia sem cotação */
    public function __construct(
        public string $ticker,
        public ?string $name,
        public ?Quote $quote,
        public FavoriteSettings $settings,
        public ?FundSnapshot $snapshot,
        public array $rules,
    ) {}
}

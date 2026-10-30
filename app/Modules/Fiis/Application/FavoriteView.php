<?php

namespace App\Modules\Fiis\Application;

final readonly class FavoriteView
{
    public function __construct(
        public string $ticker,
        public ?string $name,
        public ?Quote $quote,
    ) {}
}

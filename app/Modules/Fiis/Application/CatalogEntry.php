<?php

namespace App\Modules\Fiis\Application;

final readonly class CatalogEntry
{
    public function __construct(
        public string $ticker,
        public string $name,
    ) {}
}

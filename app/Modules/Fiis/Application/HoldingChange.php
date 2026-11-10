<?php

namespace App\Modules\Fiis\Application;

final readonly class HoldingChange
{
    public function __construct(
        public string $ticker,
        /** `null` quando o fundo não estava na carteira. */
        public ?int $before,
        public int $after,
        public bool $removed,
    ) {}
}

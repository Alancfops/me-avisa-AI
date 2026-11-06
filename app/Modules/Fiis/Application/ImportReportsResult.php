<?php

namespace App\Modules\Fiis\Application;

final readonly class ImportReportsResult
{
    /** @param list<string> $ambiguousTickers */
    public function __construct(
        public int $imported,
        public array $ambiguousTickers,
    ) {}
}

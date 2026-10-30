<?php

namespace App\Modules\Fiis\Application;

final readonly class HealthReport
{
    /** @param list<SourceStatus> $sources */
    public function __construct(
        public array $sources,
        public ?QuotaUsage $usage,
    ) {}
}

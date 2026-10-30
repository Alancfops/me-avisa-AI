<?php

namespace App\Modules\Fiis\Application;

final readonly class SourceHealth
{
    public function __construct(
        private SourceLog $log,
        private QuotaReader $quota,
    ) {}

    public function handle(): HealthReport
    {
        return new HealthReport(
            array_map($this->log->status(...), Source::cases()),
            $this->quota->usage(),
        );
    }
}

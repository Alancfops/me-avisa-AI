<?php

namespace Tests\Support\Fiis;

use App\Modules\Fiis\Application\ReportRepository;
use App\Modules\Fiis\Domain\BookValueReport;

final class InMemoryReports implements ReportRepository
{
    /** @param array<string, list<BookValueReport>> $reports */
    public function __construct(public array $reports = []) {}

    public function bookValues(string $ticker): array
    {
        return $this->reports[$ticker] ?? [];
    }

    public function monthlyYields(string $ticker): array
    {
        return [];
    }

    public function save(array $reports): void {}

    public function ambiguousTickers(): array
    {
        return [];
    }
}

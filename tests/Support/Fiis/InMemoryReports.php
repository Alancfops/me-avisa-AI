<?php

namespace Tests\Support\Fiis;

use App\Modules\Fiis\Application\FundProfile;
use App\Modules\Fiis\Application\ReportRepository;
use App\Modules\Fiis\Domain\BookValueReport;
use App\Modules\Fiis\Domain\FundKind;
use App\Modules\Fiis\Domain\MonthlyYield;

final class InMemoryReports implements ReportRepository
{
    /**
     * @param  array<string, list<BookValueReport>>  $reports
     * @param  array<string, list<MonthlyYield>>  $yields
     */
    public function __construct(public array $reports = [], public array $yields = []) {}

    public function bookValues(string $ticker): array
    {
        return $this->reports[$ticker] ?? [];
    }

    public function monthlyYields(string $ticker): array
    {
        return $this->yields[$ticker] ?? [];
    }

    public function profile(string $ticker): ?FundProfile
    {
        return null;
    }

    public function allBookValues(): array
    {
        return $this->reports;
    }

    public function allMonthlyYields(): array
    {
        return [];
    }

    public function profiles(): array
    {
        return [];
    }

    public function kinds(): array
    {
        return [];
    }

    public function kindOf(string $ticker): ?FundKind
    {
        return null;
    }

    public function save(array $reports): void {}

    public function ambiguousTickers(): array
    {
        return [];
    }
}

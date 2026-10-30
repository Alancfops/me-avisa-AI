<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use App\Modules\Fiis\Application\ReportRepository;
use App\Modules\Fiis\Domain\BookValueReport;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\MonthlyYield;
use Illuminate\Support\Facades\DB;

final class EloquentReportRepository implements ReportRepository
{
    public function bookValues(string $ticker): array
    {
        if ($this->isAmbiguous($ticker)) {
            return [];
        }

        return array_values(FundReportModel::query()
            ->where('ticker', $ticker)
            ->whereNotNull('book_value')
            ->get()
            ->map(fn (FundReportModel $row): BookValueReport => new BookValueReport(
                $row->month,
                $row->delivered_on,
                Money::fromString((string) $row->book_value),
            ))
            ->all());
    }

    public function monthlyYields(string $ticker): array
    {
        if ($this->isAmbiguous($ticker)) {
            return [];
        }

        return array_values(FundReportModel::query()
            ->where('ticker', $ticker)
            ->whereNotNull('dividend_yield')
            ->get()
            ->map(fn (FundReportModel $row): MonthlyYield => new MonthlyYield(
                $row->month,
                $row->delivered_on,
                (string) $row->dividend_yield,
            ))
            ->all());
    }

    public function save(array $reports): void
    {
        DB::transaction(function () use ($reports): void {
            $versions = FundReportModel::query()
                ->get(['cnpj', 'month', 'version'])
                ->mapWithKeys(fn (FundReportModel $row): array => ["{$row->cnpj}|{$row->month}" => $row->version])
                ->all();

            $rows = [];

            foreach ($reports as $report) {
                $record = $report->record;

                if (($versions["{$record->cnpj}|{$record->month}"] ?? 0) <= $record->version) {
                    $rows[] = [
                        'cnpj' => $record->cnpj,
                        'ticker' => $report->ticker,
                        'month' => $record->month,
                        'version' => $record->version,
                        'delivered_on' => $record->deliveredOn,
                        'book_value' => $record->bookValue,
                        'dividend_yield' => $record->dividendYield,
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                FundReportModel::query()->upsert($chunk, ['cnpj', 'month'], ['ticker', 'version', 'delivered_on', 'book_value', 'dividend_yield']);
            }
        });
    }

    public function ambiguousTickers(): array
    {
        return array_values(FundReportModel::query()
            ->select('ticker')
            ->groupBy('ticker')
            ->havingRaw('count(distinct cnpj) > 1')
            ->orderBy('ticker')
            ->pluck('ticker')
            ->all());
    }

    private function isAmbiguous(string $ticker): bool
    {
        return FundReportModel::query()->where('ticker', $ticker)->distinct()->count('cnpj') > 1;
    }
}

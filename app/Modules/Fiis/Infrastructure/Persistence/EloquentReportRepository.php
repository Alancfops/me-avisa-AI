<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use App\Modules\Fiis\Application\FundProfile;
use App\Modules\Fiis\Application\ReportRepository;
use App\Modules\Fiis\Domain\BookValueReport;
use App\Modules\Fiis\Domain\FundKind;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\MonthlyYield;
use App\Modules\Fiis\Domain\Portfolio;
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

    public function profile(string $ticker): ?FundProfile
    {
        if ($this->isAmbiguous($ticker)) {
            return null;
        }

        $row = FundReportModel::query()
            ->where('ticker', $ticker)
            ->where(fn ($query) => $query
                ->whereNotNull('segment')
                ->orWhereNotNull('administrator')
                ->orWhereNotNull('started_on')
                ->orWhereNotNull('net_assets')
                ->orWhereNotNull('holders'))
            ->orderByDesc('month')
            ->first();

        return $row === null ? null : new FundProfile(
            $row->month,
            $row->segment,
            $row->administrator,
            $row->started_on === null ? null : substr($row->started_on, 0, 10),
            $row->net_assets === null ? null : (string) $row->net_assets,
            $row->holders,
        );
    }

    public function kinds(): array
    {
        $ambiguous = array_flip($this->ambiguousTickers());
        $kinds = [];

        // Mais recente primeiro: o primeiro informe de cada ticker com composição decide.
        $rows = FundReportModel::query()->toBase()
            ->select(['ticker', 'total_invested', 'real_estate', 'receivables', 'fii_shares'])
            ->whereNotNull('total_invested')
            ->orderByDesc('month')
            ->cursor();

        foreach ($rows as $row) {
            if (isset($ambiguous[$row->ticker]) || array_key_exists($row->ticker, $kinds)) {
                continue;
            }

            $kind = (new Portfolio((string) $row->total_invested, (string) $row->real_estate, (string) $row->receivables, (string) $row->fii_shares))->kind();

            if ($kind !== null) {
                $kinds[$row->ticker] = $kind;
            }
        }

        ksort($kinds);

        return $kinds;
    }

    public function kindOf(string $ticker): ?FundKind
    {
        if ($this->isAmbiguous($ticker)) {
            return null;
        }

        $row = FundReportModel::query()
            ->where('ticker', $ticker)
            ->whereNotNull('total_invested')
            ->orderByDesc('month')
            ->first();

        return $row === null ? null : (new Portfolio((string) $row->total_invested, (string) $row->real_estate, (string) $row->receivables, (string) $row->fii_shares))->kind();
    }

    public function save(array $reports): void
    {
        DB::transaction(function () use ($reports): void {
            // Sem models: são milhares de linhas, e a importação roda com o limite de memória do PHP.
            $stored = [];

            foreach (FundReportModel::query()->toBase()->select(['cnpj', 'month', 'version', 'book_value', 'total_invested'])->cursor() as $row) {
                $stored["{$row->cnpj}|{$row->month}"] = [(int) $row->version, $row->book_value !== null, $row->total_invested !== null];
            }

            $rows = [];

            foreach ($reports as $report) {
                $record = $report->record;

                [$storedVersion, $hasBookValue, $hasPortfolio] = $stored["{$record->cnpj}|{$record->month}"] ?? [0, false, false];
                // Correção sem VP ou sem composição não apaga o último valor válido.
                $losesData = ($hasBookValue && $record->bookValue === null) || ($hasPortfolio && $record->portfolio === null);

                if ($storedVersion <= $record->version && ! $losesData) {
                    $rows[] = [
                        'cnpj' => $record->cnpj,
                        'ticker' => $report->ticker,
                        'month' => $record->month,
                        'version' => $record->version,
                        'delivered_on' => $record->deliveredOn,
                        'book_value' => $record->bookValue,
                        'dividend_yield' => $record->dividendYield,
                        'segment' => $record->segment,
                        'administrator' => $record->administrator,
                        'started_on' => $record->startedOn,
                        'net_assets' => $record->netAssets,
                        'holders' => $record->holders,
                        'total_invested' => $record->portfolio?->totalInvested,
                        'real_estate' => $record->portfolio?->realEstate,
                        'receivables' => $record->portfolio?->receivables,
                        'fii_shares' => $record->portfolio?->fiiShares,
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                FundReportModel::query()->upsert($chunk, ['cnpj', 'month'], ['ticker', 'version', 'delivered_on', 'book_value', 'dividend_yield', 'segment', 'administrator', 'started_on', 'net_assets', 'holders', 'total_invested', 'real_estate', 'receivables', 'fii_shares']);
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

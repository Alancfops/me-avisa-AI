<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use App\Modules\Fiis\Application\FundProfile;
use App\Modules\Fiis\Application\ReportRepository;
use App\Modules\Fiis\Domain\BookValueReport;
use App\Modules\Fiis\Domain\FundKind;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\MonthlyYield;
use App\Modules\Fiis\Domain\Portfolio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final class EloquentReportRepository implements ReportRepository
{
    private const PORTFOLIO_COLUMNS = ['total_invested', 'real_estate', 'receivables', 'fii_shares'];

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

    public function allBookValues(): array
    {
        $byTicker = [];

        foreach ($this->rows('book_value', ['book_value']) as $row) {
            $byTicker[$row->ticker][] = new BookValueReport($row->month, $row->delivered_on, Money::fromString((string) $row->book_value));
        }

        return $byTicker;
    }

    public function allMonthlyYields(): array
    {
        $byTicker = [];

        foreach ($this->rows('dividend_yield', ['dividend_yield']) as $row) {
            $byTicker[$row->ticker][] = new MonthlyYield($row->month, $row->delivered_on, (string) $row->dividend_yield);
        }

        return $byTicker;
    }

    public function profiles(): array
    {
        $profiles = [];
        $rows = $this->rows(null, ['segment', 'administrator', 'started_on', 'net_assets', 'holders'], 'desc')
            ->filter(fn ($row): bool => $row->segment !== null || $row->administrator !== null || $row->started_on !== null || $row->net_assets !== null || $row->holders !== null);

        foreach ($rows as $row) {
            // Do mais recente ao mais antigo: vale o primeiro de cada ticker.
            $profiles[$row->ticker] ??= new FundProfile(
                $row->month,
                $row->segment,
                $row->administrator,
                $row->started_on === null ? null : substr((string) $row->started_on, 0, 10),
                $row->net_assets === null ? null : (string) $row->net_assets,
                $row->holders === null ? null : (int) $row->holders,
            );
        }

        return $profiles;
    }

    /**
     * Linhas de todos os tickers não ambíguos, numa consulta, sem models: a lista lê o
     * informe de todo o catálogo.
     *
     * @param  list<string>  $columns
     * @param  'asc'|'desc'  $order
     * @return Collection<int, stdClass>
     */
    private function rows(?string $required, array $columns, string $order = 'asc'): Collection
    {
        return FundReportModel::query()->toBase()
            ->select(['ticker', 'month', 'delivered_on', ...$columns])
            ->whereNotIn('ticker', $this->ambiguousTickers())
            ->when($required !== null, fn ($query) => $query->whereNotNull($required))
            ->orderBy('month', $order)
            ->get();
    }

    public function kinds(): array
    {
        $ambiguous = array_flip($this->ambiguousTickers());
        $kinds = array_diff_key($this->classified(null), $ambiguous);
        ksort($kinds);

        return $kinds;
    }

    public function kindOf(string $ticker): ?FundKind
    {
        if ($this->isAmbiguous($ticker)) {
            return null;
        }

        return $this->classified($ticker)[$ticker] ?? null;
    }

    /**
     * Único caminho do tipo, para a lista e a página darem a mesma resposta: informe com
     * total investido <= 0 não tem composição, e vale o mais recente que tem.
     *
     * @return array<string, FundKind>
     */
    private function classified(?string $ticker): array
    {
        $kinds = [];

        $rows = FundReportModel::query()->toBase()
            ->select(['ticker', 'total_invested', 'real_estate', 'receivables', 'fii_shares'])
            ->where('total_invested', '>', 0)
            ->when($ticker !== null, fn ($query) => $query->where('ticker', $ticker))
            ->orderByDesc('month')
            ->cursor();

        foreach ($rows as $row) {
            if (! array_key_exists($row->ticker, $kinds)) {
                $kind = (new Portfolio((string) $row->total_invested, (string) $row->real_estate, (string) $row->receivables, (string) $row->fii_shares))->kind();

                if ($kind !== null) {
                    $kinds[$row->ticker] = $kind;
                }
            }
        }

        return $kinds;
    }

    public function save(array $reports): void
    {
        DB::transaction(function () use ($reports): void {
            // Sem models: são milhares de linhas, e a importação roda com o limite de memória do PHP.
            $stored = [];

            foreach (FundReportModel::query()->toBase()->select(['cnpj', 'month', 'version', 'book_value'])->cursor() as $row) {
                $stored["{$row->cnpj}|{$row->month}"] = [(int) $row->version, $row->book_value !== null];
            }

            $rows = [];
            $rowsWithoutPortfolio = [];

            foreach ($reports as $report) {
                $record = $report->record;

                [$storedVersion, $hasBookValue] = $stored["{$record->cnpj}|{$record->month}"] ?? [0, false];
                // Correção sem VP não apaga o último VP válido. A composição não entra aqui: sem ela, a
                // linha é gravada mesmo assim (a correção do VP vale), só que preservando a composição guardada.
                $losesData = $hasBookValue && $record->bookValue === null;

                if ($storedVersion <= $record->version && ! $losesData) {
                    $row = [
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

                    if ($record->portfolio === null) {
                        $rowsWithoutPortfolio[] = $row;
                    } else {
                        $rows[] = $row;
                    }
                }
            }

            $columns = ['ticker', 'version', 'delivered_on', 'book_value', 'dividend_yield', 'segment', 'administrator', 'started_on', 'net_assets', 'holders'];

            foreach (array_chunk($rows, 500) as $chunk) {
                FundReportModel::query()->upsert($chunk, ['cnpj', 'month'], [...$columns, ...self::PORTFOLIO_COLUMNS]);
            }

            // Linha nova entra com a composição nula; linha existente mantém a que já tem.
            foreach (array_chunk($rowsWithoutPortfolio, 500) as $chunk) {
                FundReportModel::query()->upsert($chunk, ['cnpj', 'month'], $columns);
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

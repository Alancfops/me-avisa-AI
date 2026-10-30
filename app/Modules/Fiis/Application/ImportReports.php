<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\Isin;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ImportReports
{
    public function __construct(
        private FundReports $source,
        private ReportRepository $reports,
        private CatalogRepository $catalog,
        private Clock $clock,
        private LoggerInterface $logger,
        private SourceLog $sourceLog,
    ) {}

    /**
     * Importa o ano corrente e o anterior; o ano que falhar fica como estava.
     *
     *
     * @throws SourceUnavailable se nenhum ano vier
     */
    public function handle(): ImportReportsResult
    {
        $year = (int) MarketTime::local($this->clock->now())->format('Y');
        $imported = 0;
        $failures = [];

        foreach ([$year - 1, $year] as $reportYear) {
            try {
                $stored = $this->matched($this->source->forYear($reportYear));
                $this->save($stored, $reportYear);
                $imported += count($stored);
                $this->sourceLog->success(Source::Cvm);
            } catch (SourceUnavailable $failure) {
                $failures[] = $failure;
                $this->sourceLog->failure(Source::Cvm, $failure->getMessage());
                $this->logger->warning("Informes da CVM de {$reportYear} não importados: {$failure->getMessage()}");
            }
        }

        if (count($failures) === 2) {
            throw $failures[1];
        }

        return new ImportReportsResult($imported, $this->reports->ambiguousTickers());
    }

    /**
     * @param  list<StoredReport>  $stored
     *
     * @throws SourceUnavailable
     */
    private function save(array $stored, int $year): void
    {
        try {
            $this->reports->save($stored);
        } catch (Throwable $failure) {
            // O texto do banco vai só para o log: a mensagem da exceção aparece na Saúde.
            $this->logger->error("Gravação dos informes de {$year} falhou: {$failure->getMessage()}");

            throw new SourceUnavailable("Não foi possível gravar os informes de {$year}.");
        }
    }

    /**
     * @param  list<FundReportRecord>  $records
     * @return list<StoredReport>
     */
    private function matched(array $records): array
    {
        $stored = [];

        foreach ($records as $record) {
            $ticker = Isin::tickerOf($record->isin);

            if ($ticker !== null && $this->catalog->exists($ticker)) {
                $stored[] = new StoredReport($ticker, $record);
            }
        }

        return $stored;
    }
}

<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\Isin;
use Psr\Log\LoggerInterface;

final readonly class ImportReports
{
    public function __construct(
        private FundReports $source,
        private ReportRepository $reports,
        private CatalogRepository $catalog,
        private Clock $clock,
        private LoggerInterface $logger,
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
                $this->reports->save($stored);
                $imported += count($stored);
            } catch (SourceUnavailable $failure) {
                $failures[] = $failure;
                $this->logger->warning("Informes da CVM de {$reportYear} não importados: {$failure->getMessage()}");
            }
        }

        if (count($failures) === 2) {
            throw $failures[1];
        }

        return new ImportReportsResult($imported, $this->reports->ambiguousTickers());
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

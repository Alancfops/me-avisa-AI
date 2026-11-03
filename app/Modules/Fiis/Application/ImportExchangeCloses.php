<?php

namespace App\Modules\Fiis\Application;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;

final readonly class ImportExchangeCloses
{
    /** Cobre a máquina desligada por duas semanas, sem pedir arquivo velho a cada rodada. */
    private const BUSINESS_DAYS_BACK = 10;

    public function __construct(
        private ExchangeCloses $source,
        private MarketData $market,
        private ExchangeImports $imports,
        private CatalogRepository $catalog,
        private Clock $clock,
        private LoggerInterface $logger,
        private SourceLog $sourceLog,
    ) {}

    /** O dia que falha fica para a próxima rodada, e os outros seguem. */
    public function handle(): void
    {
        foreach ($this->pendingDays() as $day) {
            try {
                $this->importDay($day);
                $this->sourceLog->success(Source::B3);
            } catch (SourceUnavailable $failure) {
                $this->logger->warning("Fechamentos da B3 de {$day} não importados: {$failure->getMessage()}");
                $this->sourceLog->failure(Source::B3, $failure->getMessage());
            }
        }
    }

    private function importDay(string $day): void
    {
        $byTicker = [];
        $fileHadData = false;

        foreach ($this->source->forDay($day) as $exchangeClose) {
            $fileHadData = true;

            if ($this->catalog->exists($exchangeClose->ticker)) {
                $byTicker[$exchangeClose->ticker][] = $exchangeClose->close;
            }
        }

        foreach ($byTicker as $ticker => $closes) {
            $this->market->saveCloses($ticker, $closes);
        }

        // Sem arquivo (404) não é erro, mas também não é dia feito: a próxima rodada tenta de novo.
        if ($fileHadData) {
            $this->imports->mark($day);
        }
    }

    /** @return list<string> dias úteis ainda não importados, do mais antigo ao mais novo */
    private function pendingDays(): array
    {
        $day = MarketTime::local($this->clock->now())->setTime(0, 0);
        $pending = [];

        // A janela são os últimos dias úteis, importados ou não: ela não anda para trás.
        for ($counted = 0; $counted < self::BUSINESS_DAYS_BACK; $day = $day->modify('-1 day')) {
            if (! $this->isWeekday($day)) {
                continue;
            }

            $counted++;

            if (! $this->imports->has($day->format('Y-m-d'))) {
                $pending[] = $day->format('Y-m-d');
            }
        }

        return array_reverse($pending);
    }

    private function isWeekday(DateTimeImmutable $day): bool
    {
        return (int) $day->format('N') < 6;
    }
}

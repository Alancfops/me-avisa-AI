<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\DailyClose;
use Psr\Log\LoggerInterface;

/**
 * Primeira carga: os arquivos anuais da B3 trazem o ano anterior e o corrente, que cobrem
 * os 12 meses que a média e o gráfico da página do fundo pedem.
 */
final readonly class ImportExchangeHistory
{
    /** Um ano de FIIs são centenas de milhares de linhas: grava a cada tanto, nunca tudo de uma vez. */
    public const BATCH_SIZE = 10_000;

    /** Os 12 meses da janela mais a folga do início, com sobra para o ano ser carregado uma vez só. */
    private const HISTORY_DAYS = 300;

    public function __construct(
        private ExchangeCloses $source,
        private MarketData $market,
        private CatalogRepository $catalog,
        private Clock $clock,
        private LoggerInterface $logger,
        private SourceLog $sourceLog,
    ) {}

    public function handle(bool $force = false): void
    {
        $today = MarketTime::local($this->clock->now())->setTime(0, 0);

        if (! $force && $this->market->hasClosesOnOrBefore($today->modify('-'.self::HISTORY_DAYS.' days')->format('Y-m-d'))) {
            return;
        }

        $tickers = array_flip($this->catalog->tickers());
        $year = (int) $today->format('Y');
        $failed = false;

        foreach ([$year - 1, $year] as $loadYear) {
            try {
                $this->importYear($loadYear, $tickers);
            } catch (SourceUnavailable $failure) {
                $failed = true;
                $this->logger->warning("Histórico da B3 de {$loadYear} não importado: {$failure->getMessage()}");
                $this->sourceLog->failure(Source::B3, $failure->getMessage());
            }
        }

        // Ano que falhou deixa a Saúde com o erro e será tentado na próxima rodada.
        if (! $failed) {
            $this->sourceLog->success(Source::B3);
        }
    }

    /** @param array<string, int> $catalogTickers tickers do catálogo como chaves */
    private function importYear(int $year, array $catalogTickers): void
    {
        /** @var array<string, list<DailyClose>> $buffer */
        $buffer = [];
        $buffered = 0;

        foreach ($this->source->forYear($year) as $exchangeClose) {
            if (! isset($catalogTickers[$exchangeClose->ticker])) {
                continue;
            }

            $buffer[$exchangeClose->ticker][] = $exchangeClose->close;

            if (++$buffered >= self::BATCH_SIZE) {
                $this->flush($buffer);
                $buffer = [];
                $buffered = 0;
            }
        }

        $this->flush($buffer);
    }

    /** @param array<string, list<DailyClose>> $buffer */
    private function flush(array $buffer): void
    {
        foreach ($buffer as $ticker => $closes) {
            $this->market->saveCloses($ticker, $closes);
        }
    }
}

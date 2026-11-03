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

    public function __construct(
        private ExchangeCloses $source,
        private MarketData $market,
        private ExchangeImports $imports,
        private CatalogRepository $catalog,
        private Clock $clock,
        private LoggerInterface $logger,
        private SourceLog $sourceLog,
    ) {}

    /** Cada ano é tentado de novo, na rodada seguinte, até entrar por inteiro; `$force` recarrega os dois. */
    public function handle(bool $force = false): void
    {
        $tickers = array_flip($this->catalog->tickers());

        // Catálogo vazio: não há o que gravar, e baixar 90 MB para descartar tudo marcaria o ano como feito.
        if ($tickers === []) {
            return;
        }

        $year = (int) MarketTime::local($this->clock->now())->format('Y');
        $failed = false;
        $loaded = false;

        foreach ([$year - 1, $year] as $loadYear) {
            if (! $force && $this->imports->hasYear($loadYear)) {
                continue;
            }

            $loaded = true;

            try {
                $this->importYear($loadYear, $tickers);
                $this->imports->markYear($loadYear);
            } catch (SourceUnavailable $failure) {
                $failed = true;
                $this->logger->warning("Histórico da B3 de {$loadYear} não importado: {$failure->getMessage()}");
                $this->sourceLog->failure(Source::B3, $failure->getMessage());
            }
        }

        // Rodada sem nada a carregar não registra nada. Só sem falha: um sucesso esconderia o erro na Saúde, e o ano que falhou segue pendente.
        if ($loaded && ! $failed) {
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

<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\TradingDays;
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
        $tickers = array_flip($this->catalog->tickers());

        // Catálogo vazio: não há o que gravar, e marcar os dias como feitos esconderia os fechamentos de quando o catálogo chegar.
        if ($tickers === []) {
            return;
        }

        $failed = false;
        $read = false;

        foreach ($this->pendingDays() as $day) {
            try {
                $read = $this->importDay($day, $tickers) || $read;
            } catch (SourceUnavailable $failure) {
                $failed = true;
                $this->logger->warning("Fechamentos da B3 de {$day} não importados: {$failure->getMessage()}");
                $this->sourceLog->failure(Source::B3, $failure->getMessage());
            }
        }

        // Só no fim e sem falha: um sucesso depois da falha esconderia o erro na Saúde,
        // e o dia falho continua pendente. Só conta se algum arquivo trouxe dado: rodada de 404 ou sem dia pendente não atualizou nada.
        if ($read && ! $failed) {
            $this->sourceLog->success(Source::B3);
        }
    }

    /**
     * @param  array<string, int>  $catalogTickers  tickers do catálogo como chaves
     * @return bool se o arquivo do dia existia e trouxe dado
     */
    private function importDay(string $day, array $catalogTickers): bool
    {
        $byTicker = [];
        $fileHadData = false;

        foreach ($this->source->forDay($day) as $exchangeClose) {
            $fileHadData = true;

            if (isset($catalogTickers[$exchangeClose->ticker])) {
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

        return $fileHadData;
    }

    /** @return list<string> dias úteis ainda não importados, do mais antigo ao mais novo */
    private function pendingDays(): array
    {
        $day = MarketTime::local($this->clock->now())->setTime(0, 0);
        $pending = [];

        // A janela são os últimos dias úteis, importados ou não: ela não anda para trás.
        for ($counted = 0; $counted < self::BUSINESS_DAYS_BACK; $day = $day->modify('-1 day')) {
            $formatted = $day->format('Y-m-d');

            if (! TradingDays::isBusinessDay($formatted)) {
                continue;
            }

            $counted++;

            if (! $this->imports->has($formatted)) {
                $pending[] = $formatted;
            }
        }

        return array_reverse($pending);
    }
}

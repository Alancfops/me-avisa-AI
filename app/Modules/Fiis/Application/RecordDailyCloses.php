<?php

namespace App\Modules\Fiis\Application;

use Psr\Log\LoggerInterface;

final readonly class RecordDailyCloses
{
    public function __construct(
        private FavoriteRepository $favorites,
        private ImportPriceHistory $history,
        private SourceLog $sourceLog,
        private LoggerInterface $logger,
    ) {}

    /** Pede a janela inteira, então também preenche os dias com a máquina desligada. */
    public function handle(): void
    {
        foreach ($this->favorites->tickers() as $ticker) {
            try {
                $this->history->handle($ticker);
                $this->sourceLog->success(Source::Brapi);
            } catch (SourceUnavailable $failure) {
                $this->logger->warning("Fechamentos de {$ticker} não gravados: {$failure->getMessage()}");
                $this->sourceLog->failure(Source::Brapi, "{$ticker}: {$failure->getMessage()}");
            }
        }
    }
}

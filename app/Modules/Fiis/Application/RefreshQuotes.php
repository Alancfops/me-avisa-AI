<?php

namespace App\Modules\Fiis\Application;

use Psr\Log\LoggerInterface;

final readonly class RefreshQuotes
{
    public function __construct(
        private FavoriteRepository $favorites,
        private Quotes $quotes,
        private MarketData $market,
        private LoggerInterface $logger,
        private SourceLog $sourceLog,
    ) {}

    public function handle(): RefreshResult
    {
        $updated = [];
        $failed = [];

        foreach ($this->favorites->tickers() as $ticker) {
            try {
                $this->market->saveQuote($this->quotes->latest($ticker));
                $updated[] = $ticker;
            } catch (SourceUnavailable $failure) {
                $this->logger->warning("Cotação de {$ticker} não atualizada: {$failure->getMessage()}");
                $this->sourceLog->failure(Source::Brapi, "{$ticker}: {$failure->getMessage()}");
                $failed[] = $ticker;
            }
        }

        if ($updated !== []) {
            $this->sourceLog->success(Source::Brapi);
        }

        return new RefreshResult($updated, $failed);
    }
}

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
                $failed[] = $ticker;
            }
        }

        return new RefreshResult($updated, $failed);
    }
}

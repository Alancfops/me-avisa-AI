<?php

namespace App\Modules\Fiis\Application;

use Psr\Log\LoggerInterface;

final readonly class AddFavorite
{
    public function __construct(
        private FavoriteRepository $favorites,
        private ImportPriceHistory $history,
        private LoggerInterface $logger,
    ) {}

    public function handle(string $ticker): void
    {
        if ($this->favorites->exists($ticker)) {
            return;
        }

        $this->favorites->add($ticker);

        try {
            $this->history->handle($ticker);
        } catch (SourceUnavailable $failure) {
            // Sem histórico a média fica indisponível e a regra diz por quê; o
            // fechamento diário tenta de novo, e o favorito não se perde por isso.
            $this->logger->warning("Histórico de {$ticker} não importado: {$failure->getMessage()}");
        }
    }
}

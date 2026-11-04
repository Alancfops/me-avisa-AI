<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\PortfolioLine;

/** Uma linha da carteira com o que a tela precisa para dizer de onde veio o preço. */
final readonly class PortfolioRow
{
    public function __construct(
        public PortfolioLine $line,
        /** `null` se o fundo saiu do catálogo. */
        public ?string $name,
        /** Cotação da brapi, só de favorito. */
        public ?Quote $quote,
        /** Último fechamento, quando não há cotação. */
        public ?DailyClose $close,
    ) {}
}

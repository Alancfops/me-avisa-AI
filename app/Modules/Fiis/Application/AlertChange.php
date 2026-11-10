<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Percentage;

final readonly class AlertChange
{
    public function __construct(
        public AlertChangeStatus $status,
        public string $ticker,
        /** `null` quando o fundo não é favorito. */
        public ?FavoriteSettings $before,
        public FavoriteSettings $requested,
        /** Preço usado na conferência: a cotação ou, sem ela, o último fechamento. */
        public ?Money $currentPrice,
        public ?Quote $quote,
        public ?DailyClose $close,
        /** @var array<string, Percentage> valores a mais de 30% do preço, com a distância */
        public array $exceeded,
        /** O alvo ou a faixa pedidos já valem no preço atual. */
        public bool $triggersNow,
        /** Sem cotação nem fechamento: a trava não pôde conferir. */
        public bool $priceUnchecked,
    ) {}
}

<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\FundKind;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Ratio;

/** Uma linha da lista de fundos: o que dá para dizer de cada fundo sem cotação do dia. */
final readonly class FundListItem
{
    public function __construct(
        public string $ticker,
        public string $name,
        public ?FundKind $kind,
        public ?string $segment,
        public ?Money $close,
        /** Pregão do fechamento, em `Y-m-d`. */
        public ?string $closeDay,
        /** Fechamento ÷ VP vigente naquele pregão. */
        public ?Ratio $priceToBook,
        /** Fração. */
        public ?string $dividendYield12m,
        /** Decimal em reais. */
        public ?string $netAssets,
        public bool $favorite,
    ) {}
}

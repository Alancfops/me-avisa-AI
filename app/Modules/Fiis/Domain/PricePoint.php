<?php

namespace App\Modules\Fiis\Domain;

final readonly class PricePoint
{
    public function __construct(
        public string $date,
        public Money $close,
        /** `null` sem informe entregue até aquele dia. */
        public ?Ratio $priceToBook,
    ) {}
}

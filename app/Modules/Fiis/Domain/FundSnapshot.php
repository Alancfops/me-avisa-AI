<?php

namespace App\Modules\Fiis\Domain;

final readonly class FundSnapshot
{
    public function __construct(
        public string $ticker,
        public Money $price,
    ) {}
}

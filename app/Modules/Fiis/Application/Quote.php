<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Percentage;
use DateTimeImmutable;

final readonly class Quote
{
    public function __construct(
        public string $ticker,
        public Money $price,
        public ?Percentage $changePercent,
        public ?int $volume,
        /** Horário informado pela fonte, e não o da consulta. */
        public DateTimeImmutable $quotedAt,
    ) {}
}

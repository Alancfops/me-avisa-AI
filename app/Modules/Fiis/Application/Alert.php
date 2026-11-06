<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\RuleKind;
use DateTimeImmutable;

/** O registro que os módulos de Telegram e MCP vão consumir. */
final readonly class Alert
{
    public function __construct(
        public string $ticker,
        public RuleKind $rule,
        public Money $price,
        public string $reference,
        public string $reason,
        public DateTimeImmutable $occurredAt,
        public DateTimeImmutable $quotedAt,
    ) {}
}

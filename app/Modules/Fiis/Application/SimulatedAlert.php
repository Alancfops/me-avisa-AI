<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\RuleKind;

final readonly class SimulatedAlert
{
    public function __construct(
        public string $day,
        public RuleKind $rule,
        public Money $price,
        public string $reason,
    ) {}
}

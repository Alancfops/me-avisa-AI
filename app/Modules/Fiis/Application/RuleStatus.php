<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\RuleKind;
use App\Modules\Fiis\Domain\RuleResult;

final readonly class RuleStatus
{
    public function __construct(
        public RuleKind $rule,
        public RuleResult $result,
    ) {}
}

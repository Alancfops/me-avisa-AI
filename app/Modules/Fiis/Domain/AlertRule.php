<?php

namespace App\Modules\Fiis\Domain;

interface AlertRule
{
    public function evaluate(FundSnapshot $fund): RuleResult;
}

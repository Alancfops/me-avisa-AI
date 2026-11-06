<?php

namespace App\Modules\Fiis\Domain;

/**
 * Preço alvo: dispara quando preço ≤ alvo (docs/fiis/alert.md, seção 1).
 */
final readonly class PriceTargetRule implements AlertRule
{
    public function __construct(private Money $target) {}

    public function evaluate(FundSnapshot $fund): RuleResult
    {
        return $fund->price->isAtMost($this->target)
            ? RuleResult::matched("Preço {$fund->price} no alvo {$this->target}", "alvo {$this->target}")
            : RuleResult::notMatched();
    }
}

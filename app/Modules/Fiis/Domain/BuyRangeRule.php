<?php

namespace App\Modules\Fiis\Domain;

/**
 * Faixa de compra: mínimo ≤ preço ≤ máximo (docs/fiis/alert.md, seção 2).
 */
final readonly class BuyRangeRule implements AlertRule
{
    public function __construct(
        private Money $min,
        private Money $max,
    ) {}

    public function evaluate(FundSnapshot $fund): RuleResult
    {
        if (! $this->min->isAtMost($fund->price) || ! $fund->price->isAtMost($this->max)) {
            return RuleResult::notMatched();
        }

        $range = "{$this->min}–{$this->max}";

        return RuleResult::matched("Preço {$fund->price} dentro da faixa {$range}", "faixa {$range}");
    }
}

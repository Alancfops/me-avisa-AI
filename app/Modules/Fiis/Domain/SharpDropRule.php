<?php

namespace App\Modules\Fiis\Domain;

/**
 * Queda forte: variação do pregão estritamente abaixo de −3%; exatamente −3% não
 * dispara (docs/fiis/alert.md, seção 4).
 */
final readonly class SharpDropRule implements AlertRule
{
    public const SHARP_DROP_PERCENT = '-3';

    public function evaluate(FundSnapshot $fund): RuleResult
    {
        if ($fund->changePercent === null) {
            return RuleResult::unavailable('Variação do dia indisponível.');
        }

        if (! $fund->changePercent->isBelow(Percentage::fromString(self::SHARP_DROP_PERCENT))) {
            return RuleResult::notMatched();
        }

        return RuleResult::matched(
            "Queda de {$fund->changePercent->magnitude()} no dia, preço {$fund->price}",
            'variação do dia abaixo de −3%',
        );
    }
}

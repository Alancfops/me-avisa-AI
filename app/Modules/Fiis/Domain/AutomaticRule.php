<?php

namespace App\Modules\Fiis\Domain;

/**
 * Regra automática: preço < média de 30 pregões × 0,95 e P/VP < mediana do próprio
 * fundo em 12 meses; com menos de 60 pregões, P/VP < 1,0 (docs/fiis/alert.md, seção 3).
 */
final readonly class AutomaticRule implements AlertRule
{
    public const AVERAGE_FACTOR = '0.95';

    public const MIN_TRADING_DAYS_FOR_MEDIAN = 60;

    public const FALLBACK_PRICE_TO_BOOK = '1.0';

    private const FALLBACK_NOTE = 'Comparando com P/VP 1,0 até juntar histórico.';

    public function evaluate(FundSnapshot $fund): RuleResult
    {
        if ($fund->priceToBook === null) {
            return RuleResult::unavailable('VP indisponível: sem o informe da CVM não dá para calcular o P/VP.');
        }

        if ($fund->closesAreStale) {
            $day = implode('/', array_reverse(explode('-', (string) $fund->lastCloseDay)));

            return RuleResult::unavailable("Fechamentos desatualizados: o último é de {$day}, e a média de 30 pregões ficaria velha.");
        }

        if ($fund->average30d === null) {
            return RuleResult::unavailable('Média de 30 pregões indisponível: histórico de preços insuficiente.');
        }

        $useMedian = $fund->priceToBookDays >= self::MIN_TRADING_DAYS_FOR_MEDIAN && $fund->medianPriceToBook !== null;
        $note = $useMedian ? null : self::FALLBACK_NOTE;
        $limit = $useMedian ? $fund->medianPriceToBook : Ratio::fromString(self::FALLBACK_PRICE_TO_BOOK);
        $limitName = $useMedian ? "da mediana {$limit}" : "de P/VP {$limit}";
        $priceLimit = $fund->average30d->times(self::AVERAGE_FACTOR);

        if (! $fund->price->isLessThan($priceLimit) || ! $fund->priceToBook->isBelow($limit)) {
            return RuleResult::notMatched($note);
        }

        return RuleResult::matched(
            "Preço {$fund->price} abaixo de {$priceLimit} (média de 30 pregões × 0,95) e P/VP {$fund->priceToBook} abaixo {$limitName}",
            "média 30d {$fund->average30d}; P/VP {$fund->priceToBook} abaixo {$limitName}",
            $note,
        );
    }
}

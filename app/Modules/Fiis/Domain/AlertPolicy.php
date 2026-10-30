<?php

namespace App\Modules\Fiis\Domain;

/**
 * Antispam de uma regra de um favorito (docs/fiis/alert.md, "Regras de controle").
 * Zerar o estado não apaga o último aviso: teto e exceção dos 2% continuam contando
 * a partir dele quando a regra volta a valer.
 */
final readonly class AlertPolicy
{
    public const CEILING_SECONDS = 86_400;

    public const REPEAT_PRICE_FACTOR = '0.98';

    public function decide(RuleState $state, bool $matches, Money $price, int $now, ?string $eventKey): AlertDecision
    {
        if (! $matches) {
            return new AlertDecision(false, new RuleState(false, false, null, $state->lastAlertPrice, $state->lastAlertAt));
        }

        $isNewEvent = ! $state->active || $state->eventKey !== $eventKey;
        $wantsAlert = $isNewEvent || $state->pending;

        if ($this->fellEnough($state, $price) || ($wantsAlert && $this->ceilingIsOpen($state, $now))) {
            return new AlertDecision(true, new RuleState(true, false, $eventKey, $price, $now));
        }

        return new AlertDecision(false, new RuleState(true, $wantsAlert, $eventKey, $state->lastAlertPrice, $state->lastAlertAt));
    }

    private function fellEnough(RuleState $state, Money $price): bool
    {
        return $state->lastAlertPrice !== null
            && $price->isAtMost($state->lastAlertPrice->times(self::REPEAT_PRICE_FACTOR));
    }

    private function ceilingIsOpen(RuleState $state, int $now): bool
    {
        return $state->lastAlertAt === null || $now - $state->lastAlertAt >= self::CEILING_SECONDS;
    }
}

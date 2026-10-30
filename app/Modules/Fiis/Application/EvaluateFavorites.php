<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\AlertPolicy;
use App\Modules\Fiis\Domain\RuleKind;
use App\Modules\Fiis\Domain\RuleResult;

final readonly class EvaluateFavorites
{
    public function __construct(
        private FavoriteRepository $favorites,
        private MarketData $market,
        private AlertRepository $alerts,
        private BuildFundSnapshot $snapshots,
        private Clock $clock,
    ) {}

    public function handle(): void
    {
        foreach ($this->favorites->tickers() as $ticker) {
            $quote = $this->market->latestQuote($ticker);
            $evaluated = $this->favorites->lastEvaluatedQuoteAt($ticker);

            // Feriado e bolsa fechada repetem a mesma cotação: sem novidade não há o
            // que avaliar, mesmo que o relógio já tenha aberto o teto de 24 h.
            if ($quote !== null && ($evaluated === null || $quote->quotedAt > $evaluated)) {
                $this->evaluate($quote);
            }
        }
    }

    /** Ao mudar a configuração: reavalia com a última cotação, mesmo sem novidade. */
    public function handleNow(string $ticker): void
    {
        $quote = $this->market->latestQuote($ticker);

        if ($quote !== null) {
            $this->evaluate($quote);
        }
    }

    private function evaluate(Quote $quote): void
    {
        $settings = $this->favorites->settings($quote->ticker);

        if ($settings === null) {
            return;
        }

        $fund = $this->snapshots->handle($quote);

        foreach ($settings->rules() as $kindValue => $rule) {
            $this->applyPolicy($quote, RuleKind::from($kindValue), $rule->evaluate($fund));
        }

        $this->favorites->markEvaluated($quote->ticker, $quote->quotedAt);
    }

    private function applyPolicy(Quote $quote, RuleKind $kind, RuleResult $result): void
    {
        $now = $this->clock->now();
        $decision = (new AlertPolicy)->apply(
            $kind,
            $result,
            $this->alerts->state($quote->ticker, $kind),
            $quote->price,
            $now->getTimestamp(),
            MarketTime::day($quote->quotedAt),
        );

        if ($decision === null) {
            return;
        }

        $alert = $decision->alert
            ? new Alert($quote->ticker, $kind, $quote->price, (string) $result->reference, (string) $result->reason, $now, $quote->quotedAt)
            : null;

        $this->alerts->apply($quote->ticker, $kind, $decision->state, $alert);
    }
}

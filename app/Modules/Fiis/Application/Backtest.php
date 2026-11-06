<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\AlertPolicy;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\FundSnapshot;
use App\Modules\Fiis\Domain\Percentage;
use App\Modules\Fiis\Domain\RuleKind;
use App\Modules\Fiis\Domain\RuleState;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Passa as mesmas regras e o mesmo antispam sobre fechamentos passados, guardando o
 * estado em memória: só lê, para o simulador nunca mexer nos avisos de verdade.
 */
final readonly class Backtest
{
    /** Hora simulada de cada avaliação: a do fechamento gravado. */
    private const EVALUATION_TIME = ImportPriceHistory::DAY_CLOSED_AT.':00';

    private const ALL_HISTORY_FROM = '0001-01-01';

    public function __construct(
        private FavoriteRepository $favorites,
        private MarketData $market,
        private Quotes $quotes,
        private ReportRepository $reports,
        private Clock $clock,
    ) {}

    /** @throws SourceUnavailable sem histórico guardado e com a fonte fora */
    public function handle(string $ticker): BacktestResult
    {
        $closes = $this->closes($ticker);
        $settings = $this->favorites->settings($ticker) ?? FavoriteSettings::default();
        $reports = $this->reports->bookValues($ticker);
        $policy = new AlertPolicy;

        /** @var array<string, RuleState> $states */
        $states = [];
        $alerts = [];
        $unavailable = [];
        $previous = null;

        foreach ($closes as $close) {
            if ($previous !== null) {
                $change = Percentage::change($previous->close, $close->close);
                $fund = FundSnapshot::fromHistory($ticker, $close->close, $change, $close->date, $closes, $reports);

                foreach ($settings->rules() as $kindValue => $rule) {
                    $kind = RuleKind::from($kindValue);
                    $result = $rule->evaluate($fund);
                    $decision = $policy->apply($kind, $result, $states[$kindValue] ?? RuleState::fresh(), $close->close, $this->moment($close->date), $close->date);

                    if ($decision === null) {
                        $unavailable[(string) $result->unavailableReason] = true;

                        continue;
                    }

                    $states[$kindValue] = $decision->state;

                    if ($decision->alert) {
                        $alerts[] = new SimulatedAlert($close->date, $kind, $close->close, (string) $result->reason);
                    }
                }
            }

            $previous = $close;
        }

        return new BacktestResult(count($closes), $alerts, array_keys($unavailable));
    }

    /** @return list<DailyClose> do mais antigo ao mais novo */
    private function closes(string $ticker): array
    {
        $today = MarketTime::day($this->clock->now());
        $stored = $this->market->closesBefore($ticker, $today, self::ALL_HISTORY_FROM);

        if ($stored !== []) {
            return $stored;
        }

        // A barra de hoje pode ser o pregão aberto, e não um fechamento.
        return array_values(array_filter(
            $this->quotes->history($ticker),
            fn (DailyClose $close): bool => $close->date < $today,
        ));
    }

    private function moment(string $day): int
    {
        return (new DateTimeImmutable("{$day} ".self::EVALUATION_TIME, new DateTimeZone(MarketTime::ZONE)))->getTimestamp();
    }
}

<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use App\Modules\Fiis\Application\Alert;
use App\Modules\Fiis\Application\AlertRepository;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\RuleKind;
use App\Modules\Fiis\Domain\RuleState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentAlertRepository implements AlertRepository
{
    public function state(string $ticker, RuleKind $rule): RuleState
    {
        $row = RuleStateModel::query()->where('ticker', $ticker)->where('rule', $rule->value)->first();

        if ($row === null) {
            return RuleState::fresh();
        }

        return new RuleState(
            $row->active,
            $row->pending,
            $row->event_key,
            $row->last_alert_price === null ? null : Money::fromString($row->last_alert_price),
            $row->last_alert_at?->getTimestamp(),
        );
    }

    public function apply(string $ticker, RuleKind $rule, RuleState $state, ?Alert $alert): void
    {
        DB::transaction(function () use ($ticker, $rule, $state, $alert): void {
            RuleStateModel::query()->updateOrCreate(['ticker' => $ticker, 'rule' => $rule->value], [
                'active' => $state->active,
                'pending' => $state->pending,
                'event_key' => $state->eventKey,
                'last_alert_price' => $state->lastAlertPrice?->toDecimal(),
                'last_alert_at' => $state->lastAlertAt === null ? null : CarbonImmutable::createFromTimestamp($state->lastAlertAt),
            ]);

            if ($alert !== null) {
                AlertModel::query()->create([
                    'ticker' => $alert->ticker,
                    'rule' => $alert->rule->value,
                    'price' => $alert->price->toDecimal(),
                    'reference' => $alert->reference,
                    'reason' => $alert->reason,
                    'occurred_at' => $alert->occurredAt,
                    'quoted_at' => $alert->quotedAt,
                ]);
            }
        });
    }

    public function resetStates(string $ticker, array $rules): void
    {
        RuleStateModel::query()
            ->where('ticker', $ticker)
            ->whereIn('rule', array_map(fn (RuleKind $rule): string => $rule->value, $rules))
            ->delete();
    }

    public function resetAllStates(string $ticker): void
    {
        RuleStateModel::query()->where('ticker', $ticker)->delete();
    }

    public function latest(int $limit): array
    {
        return array_values(AlertModel::query()
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (AlertModel $row): Alert => new Alert(
                $row->ticker,
                RuleKind::from($row->rule),
                Money::fromString($row->price),
                $row->reference,
                $row->reason,
                $row->occurred_at->toDateTimeImmutable(),
                $row->quoted_at->toDateTimeImmutable(),
            ))
            ->all());
    }
}

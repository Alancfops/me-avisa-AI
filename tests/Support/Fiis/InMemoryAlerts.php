<?php

namespace Tests\Support\Fiis;

use App\Modules\Fiis\Application\Alert;
use App\Modules\Fiis\Application\AlertRepository;
use App\Modules\Fiis\Domain\RuleKind;
use App\Modules\Fiis\Domain\RuleState;

final class InMemoryAlerts implements AlertRepository
{
    /** @var list<Alert> */
    public array $alerts = [];

    /** @var array<string, RuleState> */
    public array $states = [];

    public function state(string $ticker, RuleKind $rule): RuleState
    {
        return $this->states["{$ticker}/{$rule->value}"] ?? RuleState::fresh();
    }

    public function apply(string $ticker, RuleKind $rule, RuleState $state, ?Alert $alert): void
    {
        $this->states["{$ticker}/{$rule->value}"] = $state;

        if ($alert !== null) {
            $this->alerts[] = $alert;
        }
    }

    public function resetStates(string $ticker, array $rules): void
    {
        foreach ($rules as $rule) {
            unset($this->states["{$ticker}/{$rule->value}"]);
        }
    }

    public function resetAllStates(string $ticker): void
    {
        foreach (array_keys($this->states) as $key) {
            if (str_starts_with($key, "{$ticker}/")) {
                unset($this->states[$key]);
            }
        }
    }

    public function latest(int $limit): array
    {
        return array_slice(array_reverse($this->alerts), 0, $limit);
    }
}

<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\RuleKind;
use App\Modules\Fiis\Domain\RuleState;

interface AlertRepository
{
    public function state(string $ticker, RuleKind $rule): RuleState;

    /** Grava estado e aviso juntos: um sem o outro repetiria ou perderia o aviso. */
    public function apply(string $ticker, RuleKind $rule, RuleState $state, ?Alert $alert): void;

    /** @param list<RuleKind> $rules */
    public function resetStates(string $ticker, array $rules): void;

    public function resetAllStates(string $ticker): void;

    /** @return list<Alert> do mais novo ao mais velho */
    public function latest(int $limit): array;
}

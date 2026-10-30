<?php

namespace App\Modules\Fiis\Domain;

final readonly class AlertDecision
{
    public function __construct(
        public bool $alert,
        public RuleState $state,
    ) {}
}

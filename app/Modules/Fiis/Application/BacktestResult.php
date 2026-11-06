<?php

namespace App\Modules\Fiis\Application;

final readonly class BacktestResult
{
    /**
     * @param  list<SimulatedAlert>  $alerts
     * @param  list<string>  $unavailableReasons  por que alguma regra ficou sem avaliar
     */
    public function __construct(
        public int $tradingDays,
        public array $alerts,
        public array $unavailableReasons,
    ) {}
}

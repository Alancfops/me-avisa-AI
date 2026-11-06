<?php

namespace App\Modules\Fiis\Application;

final readonly class ListAlerts
{
    private const SHOWN = 200;

    public function __construct(private AlertRepository $alerts) {}

    /** @return list<Alert> */
    public function handle(): array
    {
        return $this->alerts->latest(self::SHOWN);
    }
}

<?php

namespace Tests\Support\Fiis;

use App\Modules\Fiis\Application\Clock;
use DateTimeImmutable;

final class FixedClock implements Clock
{
    public DateTimeImmutable $current;

    public function __construct(string $now = '2026-10-30 14:00:00-03:00')
    {
        $this->current = new DateTimeImmutable($now);
    }

    public function now(): DateTimeImmutable
    {
        return $this->current;
    }
}

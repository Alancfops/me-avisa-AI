<?php

namespace App\Modules\Fiis\Infrastructure;

use App\Modules\Fiis\Application\Clock;
use DateTimeImmutable;
use Illuminate\Support\Facades\Date;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return Date::now()->toDateTimeImmutable();
    }
}

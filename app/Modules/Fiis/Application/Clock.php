<?php

namespace App\Modules\Fiis\Application;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}

<?php

namespace App\Modules\Fiis\Application;

use DateTimeImmutable;

final readonly class SourceError
{
    public function __construct(
        public string $message,
        public DateTimeImmutable $occurredAt,
    ) {}
}

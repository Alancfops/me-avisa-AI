<?php

namespace App\Modules\Fiis\Application;

use DateTimeImmutable;

final readonly class SourceStatus
{
    /** @param list<SourceError> $errors do mais novo ao mais velho */
    public function __construct(
        public Source $source,
        public ?DateTimeImmutable $lastSuccessAt,
        public array $errors,
    ) {}
}

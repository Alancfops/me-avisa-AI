<?php

namespace App\Modules\Fiis\Domain;

final readonly class RuleResult
{
    private function __construct(
        public bool $matched,
        public ?string $reason,
    ) {}

    public static function matched(string $reason): self
    {
        return new self(true, $reason);
    }

    public static function notMatched(): self
    {
        return new self(false, null);
    }
}

<?php

namespace Tests\Support\Fiis;

use App\Modules\Fiis\Application\Source;
use App\Modules\Fiis\Application\SourceError;
use App\Modules\Fiis\Application\SourceLog;
use App\Modules\Fiis\Application\SourceStatus;

final class InMemorySourceLog implements SourceLog
{
    /** @var list<array{string, string}> */
    public array $failures = [];

    /** @var list<string> */
    public array $successes = [];

    /** @var array<string, \DateTimeImmutable> */
    public array $lastSuccess = [];

    /** @var array<string, list<SourceError>> */
    public array $recentErrors = [];

    public function success(Source $source): void
    {
        $this->successes[] = $source->value;
    }

    public function failure(Source $source, string $message): void
    {
        $this->failures[] = [$source->value, $message];
    }

    public function status(Source $source): SourceStatus
    {
        return new SourceStatus($source, $this->lastSuccess[$source->value] ?? null, $this->recentErrors[$source->value] ?? []);
    }
}

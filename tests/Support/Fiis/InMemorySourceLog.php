<?php

namespace Tests\Support\Fiis;

use App\Modules\Fiis\Application\Source;
use App\Modules\Fiis\Application\SourceLog;
use App\Modules\Fiis\Application\SourceStatus;

final class InMemorySourceLog implements SourceLog
{
    /** @var list<array{string, string}> */
    public array $failures = [];

    /** @var list<string> */
    public array $successes = [];

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
        throw new \LogicException('não usado');
    }
}

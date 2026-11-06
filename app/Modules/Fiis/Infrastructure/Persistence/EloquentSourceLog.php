<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use App\Modules\Fiis\Application\Clock;
use App\Modules\Fiis\Application\Source;
use App\Modules\Fiis\Application\SourceError;
use App\Modules\Fiis\Application\SourceLog;
use App\Modules\Fiis\Application\SourceStatus;

final readonly class EloquentSourceLog implements SourceLog
{
    private const KEPT_DAYS = 30;

    private const ERRORS_SHOWN = 10;

    public function __construct(private Clock $clock) {}

    public function success(Source $source): void
    {
        $this->record($source, 'success', null);
    }

    public function failure(Source $source, string $message): void
    {
        $this->record($source, 'failure', $message);
    }

    public function status(Source $source): SourceStatus
    {
        $lastSuccess = SourceEventModel::query()
            ->where('source', $source->value)
            ->where('kind', 'success')
            ->orderByDesc('occurred_at')
            ->first();

        $errors = SourceEventModel::query()
            ->where('source', $source->value)
            ->where('kind', 'failure')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::ERRORS_SHOWN)
            ->get()
            ->map(fn (SourceEventModel $row): SourceError => new SourceError((string) $row->message, $row->occurred_at->toDateTimeImmutable()))
            ->all();

        return new SourceStatus($source, $lastSuccess?->occurred_at->toDateTimeImmutable(), array_values($errors));
    }

    private function record(Source $source, string $kind, ?string $message): void
    {
        $now = $this->clock->now();

        SourceEventModel::query()->create([
            'source' => $source->value,
            'kind' => $kind,
            'message' => $message,
            'occurred_at' => $now,
        ]);

        SourceEventModel::query()
            ->where('occurred_at', '<', $now->modify('-'.self::KEPT_DAYS.' days')->format(DATE_ATOM))
            ->delete();
    }
}

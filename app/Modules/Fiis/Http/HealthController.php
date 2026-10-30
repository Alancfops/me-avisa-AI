<?php

namespace App\Modules\Fiis\Http;

use App\Modules\Fiis\Application\MarketTime;
use App\Modules\Fiis\Application\SourceError;
use App\Modules\Fiis\Application\SourceHealth;
use App\Modules\Fiis\Application\SourceStatus;
use Inertia\Inertia;
use Inertia\Response;

final class HealthController
{
    public function __invoke(SourceHealth $health): Response
    {
        $report = $health->handle();

        return Inertia::render('health', [
            'sources' => array_map(fn (SourceStatus $status): array => [
                'source' => $status->source->value,
                'lastSuccessAt' => $status->lastSuccessAt === null ? null : MarketTime::local($status->lastSuccessAt)->format(DATE_ATOM),
                'errors' => array_map(fn (SourceError $error): array => [
                    'message' => $error->message,
                    'occurredAt' => MarketTime::local($error->occurredAt)->format(DATE_ATOM),
                ], $status->errors),
            ], $report->sources),
            'usage' => $report->usage === null ? null : [
                'plan' => $report->usage->plan,
                'limit' => $report->usage->limit,
                'used' => $report->usage->used,
                'remaining' => $report->usage->remaining,
            ],
        ]);
    }
}

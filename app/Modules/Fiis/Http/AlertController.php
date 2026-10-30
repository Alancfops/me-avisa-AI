<?php

namespace App\Modules\Fiis\Http;

use App\Modules\Fiis\Application\Alert;
use App\Modules\Fiis\Application\ListAlerts;
use App\Modules\Fiis\Application\MarketTime;
use Inertia\Inertia;
use Inertia\Response;

final class AlertController
{
    public function __invoke(ListAlerts $listAlerts): Response
    {
        return Inertia::render('alerts', [
            'alerts' => array_map(fn (Alert $alert): array => [
                'ticker' => $alert->ticker,
                'rule' => $alert->rule->value,
                'price' => $alert->price->toDecimal(),
                'reference' => $alert->reference,
                'reason' => $alert->reason,
                'occurredAt' => MarketTime::local($alert->occurredAt)->format(DATE_ATOM),
                'quotedAt' => MarketTime::local($alert->quotedAt)->format(DATE_ATOM),
            ], $listAlerts->handle()),
        ]);
    }
}

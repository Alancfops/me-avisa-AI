<?php

use App\Modules\Fiis\Application\MarketTime;
use Illuminate\Support\Facades\Schedule;

Schedule::command('fiis:refresh-quotes')
    ->timezone(MarketTime::ZONE)
    ->everyThirtyMinutes()
    ->weekdays()
    ->between('10:00', '18:00');

// Depois do leilão de fechamento: é o horário a partir do qual a barra do dia vale
// como fechamento (ImportPriceHistory::DAY_CLOSED_AT).
Schedule::command('fiis:record-closes')
    ->timezone(MarketTime::ZONE)
    ->weekdays()
    ->dailyAt('19:00');

Schedule::command('fiis:sync-catalog')
    ->timezone(MarketTime::ZONE)
    ->dailyAt('07:00');

Schedule::command('fiis:import-reports')
    ->timezone(MarketTime::ZONE)
    ->dailyAt('20:00');

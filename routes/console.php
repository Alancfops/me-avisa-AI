<?php

use App\Modules\Fiis\Application\MarketTime;
use Illuminate\Support\Facades\Schedule;

// Definido antes da cotação, porque a agenda roda em ordem no mesmo minuto. Fora do
// pregão também, para recuperar o que se perdeu com a máquina desligada. Só gasta
// requisição quando o histórico está atrasado.
Schedule::command('fiis:catch-up')
    ->timezone(MarketTime::ZONE)
    ->everyThirtyMinutes();

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

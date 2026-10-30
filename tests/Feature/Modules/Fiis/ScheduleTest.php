<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;

afterEach(fn () => Carbon::setTestNow());

function scheduled(string $command): Event
{
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains((string) $event->command, $command));

    expect($event)->not->toBeNull();

    return $event;
}

function runsAt(string $command, string $moment): bool
{
    Carbon::setTestNow($moment);

    // O `between` guarda o "agora" ao definir o evento. Em produção cada schedule:run sobe
    // do zero; aqui a agenda precisa ser redefinida depois de fixar o horário.
    app()->forgetInstance(Schedule::class);
    Facade::clearResolvedInstance(Schedule::class);
    require base_path('routes/console.php');

    $event = scheduled($command);

    return $event->isDue(app()) && $event->filtersPass(app());
}

// 2026-10-30 é sexta. Os horários são de São Paulo, e o relógio do servidor pode estar em UTC.
it('refreshes quotes every 30 minutes, 10:00 to 18:00, Monday to Friday', function (string $moment, bool $expected) {
    expect(runsAt('fiis:refresh-quotes', $moment))->toBe($expected);
})->with([
    'antes' => ['2026-10-30 09:30:00-03:00', false],
    'abre' => ['2026-10-30 10:00:00-03:00', true],
    'meia hora' => ['2026-10-30 10:30:00-03:00', true],
    'fora do passo' => ['2026-10-30 10:15:00-03:00', false],
    'fecha' => ['2026-10-30 18:00:00-03:00', true],
    'depois' => ['2026-10-30 18:30:00-03:00', false],
    'sábado' => ['2026-10-31 10:00:00-03:00', false],
    'domingo' => ['2026-11-01 10:00:00-03:00', false],
    'segunda' => ['2026-11-02 10:00:00-03:00', true],
    'mesmo instante em UTC' => ['2026-10-30 13:00:00+00:00', true],
    '10:00 em UTC são 7:00 aqui' => ['2026-10-30 10:00:00+00:00', false],
]);

it('uses the Sao Paulo timezone', function (string $command) {
    expect(scheduled($command)->timezone)->toBe('America/Sao_Paulo');
})->with(['fiis:refresh-quotes', 'fiis:record-closes', 'fiis:sync-catalog', 'fiis:import-reports']);

it('runs the daily jobs once a day, outside the session', function (string $command, string $time) {
    $due = [];

    foreach (range(0, 23) as $hour) {
        foreach ([0, 30] as $minute) {
            $moment = sprintf('2026-10-30 %02d:%02d:00-03:00', $hour, $minute);

            if (runsAt($command, $moment)) {
                $due[] = sprintf('%02d:%02d', $hour, $minute);
            }
        }
    }

    expect($due)->toBe([$time]);
})->with([
    ['fiis:record-closes', '19:00'],
    ['fiis:sync-catalog', '07:00'],
    ['fiis:import-reports', '20:00'],
]);

it('catches up before refreshing, on every half hour of any day', function () {
    $commands = collect(app(Schedule::class)->events())->pluck('command')->map(fn ($c) => (string) $c);

    expect($commands->search(fn ($c) => str_contains($c, 'fiis:catch-up')))
        ->toBeLessThan($commands->search(fn ($c) => str_contains($c, 'fiis:refresh-quotes')))
        ->and(runsAt('fiis:catch-up', '2026-10-31 21:30:00-03:00'))->toBeTrue()
        ->and(runsAt('fiis:catch-up', '2026-10-31 21:15:00-03:00'))->toBeFalse();
});

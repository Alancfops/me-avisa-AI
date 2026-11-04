<?php

use App\Modules\Fiis\Application\Source;
use App\Modules\Telegram\Application\IncomingMessage;
use App\Modules\Telegram\Application\MessageHandler;
use App\Modules\Telegram\Application\PollOnce;
use App\Modules\Telegram\Application\TelegramUnavailable;
use Psr\Log\NullLogger;
use Tests\Support\Fiis\InMemorySourceLog;
use Tests\Support\Telegram\FakeTelegramApi;
use Tests\Support\Telegram\InMemoryTelegramState;

function pollWith(FakeTelegramApi $api, InMemoryTelegramState $state, Closure $handle, ?InMemorySourceLog $log = null): PollOnce
{
    $handler = new class($handle) implements MessageHandler
    {
        public function __construct(private Closure $handle) {}

        public function handle(IncomingMessage $message): void
        {
            ($this->handle)($message);
        }
    };

    return new PollOnce($api, $state, $handler, $log ?? new InMemorySourceLog, new NullLogger);
}

it('asks from the next update and saves the last one handled', function () {
    $api = new FakeTelegramApi([new IncomingMessage(5, 42, '/a'), new IncomingMessage(6, 42, '/b')]);
    $state = new InMemoryTelegramState;
    $state->lastUpdate = 4;
    $seen = [];

    $count = pollWith($api, $state, function (IncomingMessage $m) use (&$seen) {
        $seen[] = $m->updateId;
    })->handle();

    expect($count)->toBe(2)->and($seen)->toBe([5, 6])->and($api->offsets)->toBe([5])->and($state->lastUpdate)->toBe(6);
});

it('does not handle an update already saved', function () {
    $api = new FakeTelegramApi([new IncomingMessage(5, 42, '/a')]);
    $state = new InMemoryTelegramState;
    $state->lastUpdate = 5;
    $seen = 0;

    pollWith($api, $state, function () use (&$seen) {
        $seen++;
    })->handle();

    expect($seen)->toBe(0);
});

it('keeps going when one message fails', function () {
    $api = new FakeTelegramApi([new IncomingMessage(1, 42, 'boom'), new IncomingMessage(2, 42, 'ok')]);
    $state = new InMemoryTelegramState;
    $seen = [];

    pollWith($api, $state, function (IncomingMessage $m) use (&$seen) {
        if ($m->text === 'boom') {
            throw new RuntimeException('falhou');
        }
        $seen[] = $m->updateId;
    })->handle();

    expect($seen)->toBe([2])->and($state->lastUpdate)->toBe(2);
});

it('does not call the API without a token', function () {
    $api = new FakeTelegramApi(configured: false);

    expect(pollWith($api, new InMemoryTelegramState, fn () => null)->handle())->toBe(0)
        ->and($api->offsets)->toBe([]);
});

it('records a failure in Health when Telegram is down', function () {
    $log = new InMemorySourceLog;

    expect(fn () => pollWith(new FakeTelegramApi(down: true), new InMemoryTelegramState, fn () => null, $log)->handle())
        ->toThrow(TelegramUnavailable::class);
    expect($log->failures[0][0])->toBe(Source::Telegram->value);
});

it('records a success in Health on the first good read and after a failure, not on every read', function () {
    $log = new InMemorySourceLog;
    $api = new FakeTelegramApi;
    $poll = pollWith($api, new InMemoryTelegramState, fn () => null, $log);

    $poll->handle();
    $poll->handle();
    expect($log->successes)->toBe([Source::Telegram->value]);

    $api->down = true;
    expect(fn () => $poll->handle())->toThrow(TelegramUnavailable::class);
    $api->down = false;
    $poll->handle();

    expect($log->successes)->toBe([Source::Telegram->value, Source::Telegram->value]);
});

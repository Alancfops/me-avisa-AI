<?php

use App\Modules\Telegram\Application\CreateLinkCode;
use App\Modules\Telegram\Application\Disconnect;
use Tests\Support\Fiis\FixedClock;
use Tests\Support\Telegram\InMemoryTelegramState;

it('creates a 6 character code valid for 15 minutes and replaces the old one', function () {
    $state = new InMemoryTelegramState;
    $create = new CreateLinkCode($state, new FixedClock('2026-10-30 15:00:00-03:00'));

    $first = $create->handle();
    $second = $create->handle();

    expect($first)->toMatch('/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{6}$/')
        ->and($state->linkCode())->toBe($second)
        ->and($state->linkCodeExpiresAt())->toEqual(new DateTimeImmutable('2026-10-30 15:15:00-03:00'));
});

it('disconnects the chat', function () {
    $state = new InMemoryTelegramState;
    $state->connect(42, new DateTimeImmutable);

    (new Disconnect($state))->handle();

    expect($state->chatId())->toBeNull();
});

<?php

use App\Modules\Telegram\Infrastructure\EloquentTelegramState;
use App\Modules\Telegram\Infrastructure\TelegramStateModel;

it('keeps chat, link code and last update in one row', function () {
    $state = new EloquentTelegramState;
    $at = new DateTimeImmutable('2026-11-05 10:00:00-03:00');

    expect($state->chatId())->toBeNull()->and($state->lastUpdateId())->toBeNull();

    $state->saveLinkCode('ABC234', $at->modify('+15 minutes'));
    $state->connect(42, $at);
    $state->saveLastUpdateId(99);

    $fresh = new EloquentTelegramState;
    expect($fresh->chatId())->toBe(42)
        ->and($fresh->connectedAt())->toEqual($at)
        ->and($fresh->linkCode())->toBe('ABC234')
        ->and($fresh->linkCodeExpiresAt())->toEqual($at->modify('+15 minutes'))
        ->and($fresh->lastUpdateId())->toBe(99)
        ->and(TelegramStateModel::query()->count())->toBe(1);

    $fresh->clearLinkCode();
    $fresh->disconnect();
    expect($fresh->linkCode())->toBeNull()->and($fresh->chatId())->toBeNull()->and($fresh->connectedAt())->toBeNull()
        ->and($fresh->lastUpdateId())->toBe(99);
});

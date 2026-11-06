<?php

use App\Modules\Telegram\Infrastructure\EloquentTelegramState;
use App\Modules\Telegram\Infrastructure\TelegramStateModel;
use Illuminate\Support\Facades\DB;

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

it('keeps the bot token encrypted and starts over when it changes', function () {
    $state = new EloquentTelegramState;
    $state->connect(42, new DateTimeImmutable);
    $state->saveLinkCode('ABC234', new DateTimeImmutable('+15 minutes'));
    $state->saveLastUpdateId(99);

    $state->saveToken('123:NEW');

    $fresh = new EloquentTelegramState;
    expect($fresh->token())->toBe('123:NEW')
        ->and(DB::table('telegram_state')->value('token'))->not->toContain('123:NEW')
        ->and($fresh->chatId())->toBeNull()->and($fresh->linkCode())->toBeNull()->and($fresh->lastUpdateId())->toBeNull();

    $fresh->connect(7, new DateTimeImmutable);
    $fresh->saveLinkCode('XYZ789', new DateTimeImmutable('+15 minutes'));
    $fresh->forgetToken();

    // Sem bot não há conexão: o chat e o código pendente saem junto com o token.
    $forgotten = new EloquentTelegramState;
    expect($forgotten->token())->toBeNull()
        ->and($forgotten->chatId())->toBeNull()->and($forgotten->connectedAt())->toBeNull()
        ->and($forgotten->linkCode())->toBeNull();
});

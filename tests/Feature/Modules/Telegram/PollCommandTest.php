<?php

use App\Modules\Telegram\Application\IncomingMessage;
use App\Modules\Telegram\Application\TelegramApi;
use App\Modules\Telegram\Application\TelegramState;
use Tests\Support\Telegram\FakeTelegramApi;

it('runs one round with --once and answers', function () {
    $api = new FakeTelegramApi([new IncomingMessage(3, 42, '/start')]);
    app()->instance(TelegramApi::class, $api);

    $this->artisan('telegram:poll --once')->assertSuccessful();

    expect($api->sent[0][1])->toContain('ainda não está conectado')
        ->and(app(TelegramState::class)->lastUpdateId())->toBe(3);
});

it('exits cleanly with --once when Telegram is down', function () {
    app()->instance(TelegramApi::class, new FakeTelegramApi(down: true));

    $this->artisan('telegram:poll --once')->assertSuccessful();
});

it('sets up the bot profile when it starts', function () {
    $api = new FakeTelegramApi;
    app()->instance(TelegramApi::class, $api);

    $this->artisan('telegram:poll --once')->assertSuccessful();

    expect($api->branded[0]['name'])->toBe('Me Avisa Aí')
        ->and(array_keys($api->branded[0]['commands']))->toBe(['alertas', 'ver', 'favoritos', 'carteira', 'ajuda']);
});

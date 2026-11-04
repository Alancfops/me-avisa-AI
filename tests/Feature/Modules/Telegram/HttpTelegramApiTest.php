<?php

use App\Modules\Telegram\Application\TelegramUnavailable;
use App\Modules\Telegram\Infrastructure\HttpTelegramApi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => config(['services.telegram.url' => 'https://tg.test', 'services.telegram.token' => 'SECRET123']));

it('reads the bot profile', function () {
    Http::fake(['tg.test/botSECRET123/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'meavisa_bot', 'first_name' => 'Me Avisa']])]);

    $me = app(HttpTelegramApi::class)->me();

    expect($me->username)->toBe('meavisa_bot')->and($me->name)->toBe('Me Avisa');
});

it('long polls updates from the offset and keeps non-text updates', function () {
    Http::fake(['tg.test/botSECRET123/getUpdates*' => Http::response(['ok' => true, 'result' => [
        ['update_id' => 10, 'message' => ['chat' => ['id' => 42], 'text' => '/alertas']],
        ['update_id' => 11, 'message' => ['chat' => ['id' => 42], 'sticker' => []]],
    ]])]);

    $updates = app(HttpTelegramApi::class)->updates(10);

    expect(array_map(fn ($m) => [$m->updateId, $m->chatId, $m->text], $updates))->toBe([[10, 42, '/alertas'], [11, 42, '']]);
    Http::assertSent(fn (Request $r) => $r['offset'] === 10 && $r['timeout'] === 30);
});

it('sends HTML messages without link previews', function () {
    Http::fake(['tg.test/botSECRET123/sendMessage' => Http::response(['ok' => true, 'result' => []])]);

    app(HttpTelegramApi::class)->send(42, '<b>oi</b>');

    Http::assertSent(fn (Request $r) => $r['chat_id'] === 42 && $r['text'] === '<b>oi</b>'
        && $r['parse_mode'] === 'HTML' && $r['disable_web_page_preview'] === true);
});

it('turns API errors into a message without the token', function (mixed $response) {
    Http::fake(['tg.test/*' => $response]);

    try {
        app(HttpTelegramApi::class)->me();
        $this->fail('esperava TelegramUnavailable');
    } catch (TelegramUnavailable $e) {
        expect($e->getMessage())->not->toContain('SECRET123')->not->toBe('');
    }
})->with([
    'token inválido' => fn () => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401),
    'ok false' => fn () => Http::response(['ok' => false, 'description' => 'Bad Request'], 200),
    'fora do ar' => fn () => Http::failedConnection(),
]);

it('does nothing without a token', function () {
    config(['services.telegram.token' => null]);
    Http::fake();

    expect(app(HttpTelegramApi::class)->isConfigured())->toBeFalse();
    Http::assertNothingSent();
});

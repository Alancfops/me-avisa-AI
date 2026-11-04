<?php

use App\Modules\Telegram\Application\TelegramApi;
use App\Modules\Telegram\Application\TelegramState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\Telegram\FakeTelegramApi;

beforeEach(fn () => Carbon::setTestNow('2026-10-30 15:00:00-03:00'));
afterEach(fn () => Carbon::setTestNow());

function useBot(FakeTelegramApi $api): FakeTelegramApi
{
    app()->instance(TelegramApi::class, $api);

    return $api;
}

it('explains how to create the bot without a token', function () {
    useBot(new FakeTelegramApi(configured: false));

    $this->get('/telegram')->assertInertia(fn ($page) => $page
        ->component('telegram')
        ->where('state', 'no_token')
        ->where('bot', null)
        ->has('commands', 5));
});

it('says the token is invalid when Telegram refuses it', function () {
    useBot(new FakeTelegramApi(refused: true));

    $this->get('/telegram')->assertInertia(fn ($page) => $page->where('state', 'invalid_token'));
});

it('says Telegram is offline instead of blaming the token', function () {
    useBot(new FakeTelegramApi(down: true));

    $this->get('/telegram')->assertInertia(fn ($page) => $page->where('state', 'offline'));
});

it('tells where the token comes from', function (?string $source) {
    useBot(new FakeTelegramApi(configured: $source !== null, source: $source));

    $this->get('/telegram')->assertInertia(fn ($page) => $page->where('tokenSource', $source));
})->with([null, 'env', 'app']);

it('saves a pasted token, checks it with Telegram and sets up the bot', function () {
    $api = useBot(new FakeTelegramApi(configured: false));

    $this->post('/telegram/token', ['token' => '  123456:AAH-abc_defghijklmnopqrstuvwxyz0123  '])
        ->assertRedirect('/telegram')->assertSessionHasNoErrors();

    expect(app(TelegramState::class)->token())->toBe('123456:AAH-abc_defghijklmnopqrstuvwxyz0123')
        ->and($api->checked)->toBe(['123456:AAH-abc_defghijklmnopqrstuvwxyz0123'])
        ->and($api->branded)->toHaveCount(1);
});

it('refuses a token that is not one', function (array $body, FakeTelegramApi $api) {
    useBot($api);

    $this->post('/telegram/token', $body)->assertSessionHasErrors('token');

    expect(app(TelegramState::class)->token())->toBeNull();
})->with([
    'vazio' => [[], fn () => new FakeTelegramApi(configured: false)],
    'formato errado' => [['token' => 'meu token'], fn () => new FakeTelegramApi(configured: false)],
    'recusado pelo Telegram' => [['token' => '123456:AAH-abc_defghijklmnopqrstuvwxyz0123'], fn () => new FakeTelegramApi(configured: false, refused: true)],
    'Telegram fora do ar' => [['token' => '123456:AAH-abc_defghijklmnopqrstuvwxyz0123'], fn () => new FakeTelegramApi(configured: false, down: true)],
    'token já no .env' => [['token' => '123456:AAH-abc_defghijklmnopqrstuvwxyz0123'], fn () => new FakeTelegramApi(source: 'env')],
]);

it('forgets the token saved in the app', function () {
    useBot(new FakeTelegramApi(source: 'app'));
    app(TelegramState::class)->saveToken('123:X');

    $this->delete('/telegram/token')->assertRedirect('/telegram');

    expect(app(TelegramState::class)->token())->toBeNull();
});

it('answers whether the chat is connected, for the page to refresh itself', function () {
    $this->getJson('/telegram/estado')->assertExactJson(['connected' => false]);

    app(TelegramState::class)->connect(42, new DateTimeImmutable);

    $this->getJson('/telegram/estado')->assertExactJson(['connected' => true]);
});

it('shows a fresh code and the link while not connected', function () {
    useBot(new FakeTelegramApi);

    $this->get('/telegram')->assertInertia(fn ($page) => $page
        ->where('state', 'ready')
        ->where('bot', ['username' => 'meavisa_bot', 'name' => 'Me Avisa'])
        ->where('code', app(TelegramState::class)->linkCode())
        ->where('link', 'https://t.me/meavisa_bot?start='.app(TelegramState::class)->linkCode())
        ->where('codeExpiresAt', '2026-10-30T15:15:00-03:00'));
});

it('keeps a valid code and replaces it on request', function () {
    useBot(new FakeTelegramApi);
    $this->get('/telegram');
    $first = app(TelegramState::class)->linkCode();

    $this->get('/telegram');
    expect(app(TelegramState::class)->linkCode())->toBe($first);

    $this->post('/telegram/codigo')->assertRedirect('/telegram');
    expect(app(TelegramState::class)->linkCode())->not->toBe($first);
});

it('shows the connection, sends a test and disconnects', function () {
    $api = useBot(new FakeTelegramApi);
    app(TelegramState::class)->connect(42, new DateTimeImmutable('2026-10-30 14:00:00-03:00'));

    $this->get('/telegram')->assertInertia(fn ($page) => $page
        ->where('state', 'connected')
        ->where('connectedAt', '2026-10-30T14:00:00-03:00')
        ->where('code', null));

    $this->post('/telegram/teste')->assertRedirect('/telegram')->assertSessionHasNoErrors();
    expect($api->sent[0])->toBe([42, '✅ Mensagem de teste do Me Avisa Aí. Os avisos vão chegar aqui.']);

    $this->delete('/telegram')->assertRedirect('/telegram');
    expect(app(TelegramState::class)->chatId())->toBeNull();
});

it('refuses the test message when not connected', function () {
    useBot(new FakeTelegramApi);

    $this->post('/telegram/teste')->assertSessionHasErrors('telegram');
});

it('lists the bot in Health', function () {
    Http::fake(); // a Saúde consulta a cota da brapi

    $this->get('/saude')->assertInertia(fn ($page) => $page->where('sources', fn ($sources) => collect($sources)->pluck('source')->contains('telegram')));
});

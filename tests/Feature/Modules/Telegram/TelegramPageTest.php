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
    useBot(new FakeTelegramApi(down: true));

    $this->get('/telegram')->assertInertia(fn ($page) => $page->where('state', 'invalid_token'));
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

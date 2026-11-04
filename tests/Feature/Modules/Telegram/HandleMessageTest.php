<?php

use App\Modules\Fiis\Infrastructure\Persistence\AlertModel;
use App\Modules\Fiis\Infrastructure\Persistence\FavoriteModel;
use App\Modules\Fiis\Infrastructure\Persistence\FundModel;
use App\Modules\Fiis\Infrastructure\Persistence\QuoteModel;
use App\Modules\Telegram\Application\HandleMessage;
use App\Modules\Telegram\Application\IncomingMessage;
use App\Modules\Telegram\Application\TelegramApi;
use App\Modules\Telegram\Application\TelegramState;
use Illuminate\Support\Carbon;
use Tests\Support\Telegram\FakeTelegramApi;

beforeEach(function () {
    Carbon::setTestNow('2026-10-30 15:00:00-03:00');
    $this->api = new FakeTelegramApi;
    app()->instance(TelegramApi::class, $this->api);
    FundModel::create(['ticker' => 'KNRI11', 'name' => 'Kinea Renda Imobiliaria']);
});
afterEach(fn () => Carbon::setTestNow());

function say(string $text, int $chat = 42, int $update = 1): void
{
    app(HandleMessage::class)->handle(new IncomingMessage($update, $chat, $text));
}

function linkWith(string $code, string $expires = '2026-10-30 15:10:00-03:00'): void
{
    app(TelegramState::class)->saveLinkCode($code, new DateTimeImmutable($expires));
}

it('connects with the right code and welcomes', function () {
    linkWith('ABC234');

    say('/start abc234');

    expect(app(TelegramState::class)->chatId())->toBe(42)
        ->and(app(TelegramState::class)->linkCode())->toBeNull()
        ->and($this->api->sent[0][0])->toBe(42)
        ->and($this->api->sent[0][1])->toContain('✅ Conectado');
});

it('explains how to connect for a wrong, expired or missing code', function (string $text, string $expires) {
    linkWith('ABC234', $expires);

    say($text);

    expect(app(TelegramState::class)->chatId())->toBeNull()
        ->and($this->api->sent[0][1])->toContain('ainda não está conectado');
})->with([
    'errado' => ['/start ZZZ999', '2026-10-30 15:10:00-03:00'],
    'vencido' => ['/start ABC234', '2026-10-30 14:59:00-03:00'],
    'sem código' => ['/start', '2026-10-30 15:10:00-03:00'],
    'outro texto' => ['/alertas', '2026-10-30 15:10:00-03:00'],
]);

it('ignores other chats once connected', function () {
    app(TelegramState::class)->connect(42, new DateTimeImmutable);

    say('/alertas', chat: 7);
    say('/start ABC234', chat: 7);

    expect($this->api->sent)->toBe([]);
});

it('answers each command to the connected chat', function (string $text, string $expected) {
    app(TelegramState::class)->connect(42, new DateTimeImmutable);
    FavoriteModel::create(['ticker' => 'KNRI11']);
    QuoteModel::query()->create(['ticker' => 'KNRI11', 'price' => '146.46', 'change_percent' => '-0.5', 'quoted_at' => '2026-10-30T14:30:00-03:00']);

    say($text);

    expect($this->api->sent)->toHaveCount(1)->and($this->api->sent[0][1])->toContain($expected);
})->with([
    'alertas vazio' => ['/alertas', '✅ Nenhum favorito bateu regra hoje (30/10).'],
    'ver' => ['/ver knri11', '🏢 <b>KNRI11</b> · Kinea Renda Imobiliaria'],
    'ver com @bot e maiúsculas' => ['/VER@meavisa_bot KNRI11', '🏢 <b>KNRI11</b>'],
    'ver sem ticker' => ['/ver', 'Use assim: <code>/ver CÓDIGO</code>'],
    'ver fora do catálogo' => ['/ver XPTO11', '⚠️ Não achei <b>XPTO11</b>'],
    'favoritos' => ['/favoritos', '⭐ <b>Seus favoritos (1)</b>'],
    'carteira' => ['/carteira', '💼 Sua carteira está vazia'],
    'ajuda' => ['/ajuda', '<b>Comandos</b>'],
    'texto livre' => ['oi', '<b>Comandos</b>'],
]);

it('lists only alerts from today in São Paulo', function () {
    app(TelegramState::class)->connect(42, new DateTimeImmutable);
    $alert = ['ticker' => 'KNRI11', 'rule' => 'price_target', 'price' => '146.46', 'reference' => 'alvo R$ 150,00', 'reason' => 'x', 'quoted_at' => '2026-10-30T14:30:00-03:00'];
    AlertModel::query()->create([...$alert, 'occurred_at' => '2026-10-30T14:30:00-03:00']);
    AlertModel::query()->create([...$alert, 'rule' => 'sharp_drop', 'occurred_at' => '2026-10-29T14:30:00-03:00']);

    say('/alertas');

    expect($this->api->sent[0][1])->toContain('🎯 <b>KNRI11</b> · alvo')->not->toContain('queda forte');
});

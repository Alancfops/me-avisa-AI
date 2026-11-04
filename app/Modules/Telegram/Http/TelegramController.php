<?php

namespace App\Modules\Telegram\Http;

use App\Modules\Fiis\Application\Clock;
use App\Modules\Fiis\Application\MarketTime;
use App\Modules\Telegram\Application\BotProfile;
use App\Modules\Telegram\Application\CreateLinkCode;
use App\Modules\Telegram\Application\Disconnect;
use App\Modules\Telegram\Application\Messages;
use App\Modules\Telegram\Application\SendToConnectedChat;
use App\Modules\Telegram\Application\TelegramApi;
use App\Modules\Telegram\Application\TelegramState;
use App\Modules\Telegram\Application\TelegramUnavailable;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class TelegramController
{
    private const COMMANDS = [
        ['command' => '/alertas', 'example' => '/alertas', 'description' => 'Favoritos que bateram regra hoje'],
        ['command' => '/ver', 'example' => '/ver KNRI11', 'description' => 'Preço, P/VP e rendimento de um fundo'],
        ['command' => '/favoritos', 'example' => '/favoritos', 'description' => 'Seus favoritos com o preço'],
        ['command' => '/carteira', 'example' => '/carteira', 'description' => 'Valor e rendimento estimado da carteira'],
        ['command' => '/ajuda', 'example' => '/ajuda', 'description' => 'A lista de comandos'],
    ];

    public function show(TelegramApi $api, TelegramState $state, CreateLinkCode $createCode, Clock $clock): Response
    {
        [$status, $bot] = $this->status($api, $state);
        $code = null;
        $expiresAt = null;

        if ($status === 'ready') {
            $code = $state->linkCode();
            $expiresAt = $state->linkCodeExpiresAt();

            if ($code === null || $expiresAt === null || $expiresAt <= $clock->now()) {
                $code = $createCode->handle();
                $expiresAt = $state->linkCodeExpiresAt();
            }
        }

        return Inertia::render('telegram', [
            'state' => $status,
            'bot' => $bot === null ? null : ['username' => $bot->username, 'name' => $bot->name],
            'code' => $code,
            'codeExpiresAt' => self::atom($expiresAt),
            'connectedAt' => $status === 'connected' ? self::atom($state->connectedAt()) : null,
            'link' => $bot !== null && $code !== null ? "https://t.me/{$bot->username}?start={$code}" : null,
            'commands' => self::COMMANDS,
        ]);
    }

    public function newCode(CreateLinkCode $createCode): RedirectResponse
    {
        $createCode->handle();

        return redirect()->route('telegram');
    }

    public function test(SendToConnectedChat $send, Messages $messages): RedirectResponse
    {
        try {
            $sent = $send->handle($messages->test());
        } catch (TelegramUnavailable $failure) {
            return redirect()->route('telegram')->withErrors(['telegram' => $failure->getMessage()]);
        }

        return $sent
            ? redirect()->route('telegram')
            : redirect()->route('telegram')->withErrors(['telegram' => 'Conecte o bot antes de enviar a mensagem de teste.']);
    }

    public function disconnect(Disconnect $disconnect): RedirectResponse
    {
        $disconnect->handle();

        return redirect()->route('telegram');
    }

    /** @return array{string, BotProfile|null} */
    private function status(TelegramApi $api, TelegramState $state): array
    {
        if (! $api->isConfigured()) {
            return ['no_token', null];
        }

        try {
            $bot = $api->me();
        } catch (TelegramUnavailable) {
            return ['invalid_token', null];
        }

        return [$state->chatId() === null ? 'ready' : 'connected', $bot];
    }

    private static function atom(?DateTimeImmutable $at): ?string
    {
        return $at === null ? null : MarketTime::local($at)->format(DATE_ATOM);
    }
}

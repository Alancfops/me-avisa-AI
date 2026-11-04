<?php

namespace App\Modules\Telegram\Http;

use App\Modules\Fiis\Application\Clock;
use App\Modules\Fiis\Application\MarketTime;
use App\Modules\Telegram\Application\BotCommands;
use App\Modules\Telegram\Application\BotProfile;
use App\Modules\Telegram\Application\CreateLinkCode;
use App\Modules\Telegram\Application\Disconnect;
use App\Modules\Telegram\Application\InvalidTokenInput;
use App\Modules\Telegram\Application\Messages;
use App\Modules\Telegram\Application\SaveToken;
use App\Modules\Telegram\Application\SendToConnectedChat;
use App\Modules\Telegram\Application\TelegramApi;
use App\Modules\Telegram\Application\TelegramState;
use App\Modules\Telegram\Application\TelegramUnavailable;
use App\Modules\Telegram\Application\TokenRefused;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TelegramController
{
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
            'tokenSource' => $api->tokenSource(),
            'commands' => BotCommands::LIST,
        ]);
    }

    public function saveToken(Request $request, SaveToken $save): RedirectResponse
    {
        try {
            $save->handle((string) $request->string('token'));
        } catch (InvalidTokenInput $failure) {
            return back()->withErrors(['token' => $failure->getMessage()]);
        } catch (TokenRefused) {
            return back()->withErrors(['token' => 'O Telegram recusou esse token. Copie de novo a mensagem do @BotFather.']);
        } catch (TelegramUnavailable) {
            return back()->withErrors(['token' => 'Não conseguimos falar com o Telegram para conferir o token. Confira a internet e tente de novo.']);
        }

        return redirect()->route('telegram');
    }

    public function forgetToken(TelegramState $state): RedirectResponse
    {
        $state->forgetToken();

        return redirect()->route('telegram');
    }

    /** A aba pergunta de tempos em tempos para mudar para "Conectado" sozinha. */
    public function connection(TelegramState $state): JsonResponse
    {
        return response()->json(['connected' => $state->chatId() !== null]);
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
        } catch (TokenRefused) {
            return ['invalid_token', null];
        } catch (TelegramUnavailable) {
            return ['offline', null];
        }

        return [$state->chatId() === null ? 'ready' : 'connected', $bot];
    }

    private static function atom(?DateTimeImmutable $at): ?string
    {
        return $at === null ? null : MarketTime::local($at)->format(DATE_ATOM);
    }
}

<?php

namespace App\Modules\Telegram\Infrastructure;

use App\Modules\Telegram\Application\BotProfile;
use App\Modules\Telegram\Application\IncomingMessage;
use App\Modules\Telegram\Application\TelegramApi;
use App\Modules\Telegram\Application\TelegramUnavailable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class HttpTelegramApi implements TelegramApi
{
    private const POLL_SECONDS = 30;

    public function isConfigured(): bool
    {
        return $this->token() !== '';
    }

    public function me(): BotProfile
    {
        $result = $this->call('getMe', [], 10);

        return new BotProfile((string) ($result['username'] ?? ''), (string) ($result['first_name'] ?? ''));
    }

    public function updates(?int $offset): array
    {
        $params = ['timeout' => self::POLL_SECONDS, 'allowed_updates' => ['message']];

        if ($offset !== null) {
            $params['offset'] = $offset;
        }

        $messages = [];

        foreach ($this->call('getUpdates', $params, self::POLL_SECONDS + 5) as $update) {
            if (! is_array($update) || ! is_int($update['update_id'] ?? null)) {
                continue;
            }

            $chatId = $update['message']['chat']['id'] ?? null;
            $text = $update['message']['text'] ?? '';

            $messages[] = new IncomingMessage($update['update_id'], is_int($chatId) ? $chatId : 0, is_string($text) ? $text : '');
        }

        return $messages;
    }

    public function send(int $chatId, string $html): void
    {
        $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], 10);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<mixed>
     */
    private function call(string $method, array $params, int $timeout): array
    {
        if (! $this->isConfigured()) {
            throw new TelegramUnavailable('Falta o TELEGRAM_BOT_TOKEN no .env.');
        }

        $url = rtrim((string) config('services.telegram.url'), '/').'/bot'.$this->token().'/'.$method;

        try {
            $response = Http::timeout($timeout)->asJson()->post($url, $params);
        } catch (Throwable) {
            throw new TelegramUnavailable('Não conseguimos falar com o Telegram.');
        }

        return $this->result($response);
    }

    /** @return array<mixed> */
    private function result(Response $response): array
    {
        $body = $response->json();

        if ($response->status() === 401) {
            throw new TelegramUnavailable('O Telegram recusou o token. Confira o TELEGRAM_BOT_TOKEN no .env.');
        }

        if (! is_array($body) || ($body['ok'] ?? false) !== true || ! is_array($body['result'] ?? null)) {
            throw new TelegramUnavailable("O Telegram respondeu com erro (HTTP {$response->status()}).");
        }

        return $body['result'];
    }

    private function token(): string
    {
        return trim((string) config('services.telegram.token'));
    }
}

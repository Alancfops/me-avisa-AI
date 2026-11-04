<?php

namespace App\Modules\Telegram\Infrastructure;

use App\Modules\Telegram\Application\BotProfile;
use App\Modules\Telegram\Application\IncomingMessage;
use App\Modules\Telegram\Application\TelegramApi;
use App\Modules\Telegram\Application\TelegramState;
use App\Modules\Telegram\Application\TelegramUnavailable;
use App\Modules\Telegram\Application\TokenRefused;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class HttpTelegramApi implements TelegramApi
{
    private const POLL_SECONDS = 30;

    public function __construct(private TelegramState $state) {}

    public function isConfigured(): bool
    {
        return $this->token() !== '';
    }

    public function tokenSource(): ?string
    {
        if ($this->envToken() !== '') {
            return 'env';
        }

        return $this->token() === '' ? null : 'app';
    }

    public function me(): BotProfile
    {
        return $this->profile($this->call('getMe', [], 10));
    }

    public function meWith(string $token): BotProfile
    {
        return $this->profile($this->call('getMe', [], 10, $token));
    }

    public function brand(string $name, string $shortDescription, string $description, array $commands): void
    {
        $menu = [];

        foreach ($commands as $command => $text) {
            $menu[] = ['command' => $command, 'description' => $text];
        }

        $this->call('setMyCommands', ['commands' => $menu], 10);
        $this->call('setMyShortDescription', ['short_description' => $shortDescription], 10);
        $this->call('setMyDescription', ['description' => $description], 10);

        // O Telegram limita trocas de nome; só pede quando muda.
        if ($this->me()->name !== $name) {
            $this->call('setMyName', ['name' => $name], 10);
        }
    }

    public function updates(?int $offset): array
    {
        $params = ['timeout' => self::POLL_SECONDS, 'allowed_updates' => ['message']];

        if ($offset !== null) {
            $params['offset'] = $offset;
        }

        $messages = [];

        $result = $this->call('getUpdates', $params, self::POLL_SECONDS + 5);

        foreach (is_array($result) ? $result : [] as $update) {
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

    private function profile(mixed $result): BotProfile
    {
        $result = is_array($result) ? $result : [];

        return new BotProfile((string) ($result['username'] ?? ''), (string) ($result['first_name'] ?? ''));
    }

    /** @param  array<string, mixed>  $params */
    private function call(string $method, array $params, int $timeout, ?string $token = null): mixed
    {
        $token ??= $this->token();

        if ($token === '') {
            throw new TelegramUnavailable('Falta o token do bot.');
        }

        $url = rtrim((string) config('services.telegram.url'), '/').'/bot'.$token.'/'.$method;

        try {
            $response = Http::timeout($timeout)->asJson()->post($url, $params);
        } catch (Throwable) {
            throw new TelegramUnavailable('Não conseguimos falar com o Telegram.');
        }

        return $this->result($response);
    }

    private function result(Response $response): mixed
    {
        $body = $response->json();

        if ($response->status() === 401) {
            throw new TokenRefused('O Telegram recusou o token do bot.');
        }

        if (! is_array($body) || ($body['ok'] ?? false) !== true || ! array_key_exists('result', $body)) {
            throw new TelegramUnavailable("O Telegram respondeu com erro (HTTP {$response->status()}).");
        }

        return $body['result'];
    }

    /** O do `.env` tem prioridade sobre o salvo pela aba. */
    private function token(): string
    {
        return $this->envToken() !== '' ? $this->envToken() : trim((string) $this->state->token());
    }

    private function envToken(): string
    {
        return trim((string) config('services.telegram.token'));
    }
}

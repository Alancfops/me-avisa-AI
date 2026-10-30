<?php

namespace App\Modules\Fiis\Infrastructure\Brapi;

use App\Modules\Fiis\Application\SourceUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Único ponto que fala com a brapi. Nenhuma mensagem daqui inclui o corpo da
 * resposta nem a exceção original: qualquer um dos dois poderia carregar o token.
 */
final class BrapiClient
{
    private const BLOCK_KEY = 'brapi.blocked_until';

    private const TIMEOUT_SECONDS = 15;

    /**
     * @param  array<string, string|int>  $query
     * @return array<mixed>
     *
     * @throws SourceUnavailable
     */
    public function get(string $path, array $query = []): array
    {
        $this->abortWhileRateLimited();

        try {
            $response = Http::baseUrl((string) config('services.brapi.url'))
                ->withToken((string) config('services.brapi.token'))
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->get($path, $query);
        } catch (ConnectionException) {
            throw new SourceUnavailable('Não foi possível conectar à brapi.');
        }

        $this->abortOnFailureStatus($response);

        $body = $response->json();

        if (! is_array($body)) {
            throw new SourceUnavailable('A brapi enviou uma resposta inválida.');
        }

        return $body;
    }

    private function abortWhileRateLimited(): void
    {
        if (Cache::has(self::BLOCK_KEY)) {
            throw new SourceUnavailable('Limite de requisições da brapi atingido; aguardando para tentar de novo.');
        }
    }

    private function abortOnFailureStatus(Response $response): void
    {
        if ($response->status() === 429) {
            $this->blockUntilRetryAfter($response);
        }

        match (true) {
            $response->status() === 401 => throw new SourceUnavailable('A brapi recusou o token (401). Confira o BRAPI_TOKEN no .env.'),
            $response->status() === 403 => throw new SourceUnavailable('O plano da brapi não dá acesso a este dado (403).'),
            $response->status() === 404 => throw new SourceUnavailable('A brapi não encontrou este ativo (404).'),
            $response->status() === 429 => throw new SourceUnavailable('Limite de requisições da brapi atingido (429).'),
            ! $response->successful() => throw new SourceUnavailable("A brapi está indisponível ({$response->status()})."),
            default => null,
        };
    }

    /** O bloqueio sobrevive à rodada: outro comando também não pode insistir antes da hora. */
    private function blockUntilRetryAfter(Response $response): void
    {
        $until = $this->retryAfter($response->header('Retry-After'));

        if ($until !== null && $until->isFuture()) {
            Cache::put(self::BLOCK_KEY, $until->toIso8601String(), $until);
        }
    }

    /** O header aceita segundos ou uma data HTTP. */
    private function retryAfter(string $header): ?Carbon
    {
        if (ctype_digit($header)) {
            return Carbon::now()->addSeconds((int) $header);
        }

        if ($header === '') {
            return null;
        }

        try {
            return Carbon::parse($header);
        } catch (Throwable) {
            return null;
        }
    }
}

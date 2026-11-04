<?php

namespace App\Modules\Telegram\Application;

/** Bot API do Telegram, só o que o app usa. Erros viram TelegramUnavailable. */
interface TelegramApi
{
    /** Há token, no `.env` ou salvo pela aba Telegram. Sem ele, nada é pedido ao Telegram. */
    public function isConfigured(): bool;

    /** De onde vem o token em uso: `env` (tem prioridade), `app` ou null sem token. */
    public function tokenSource(): ?string;

    /**
     * @throws TokenRefused quando o Telegram recusa o token
     * @throws TelegramUnavailable quando o Telegram não responde
     */
    public function me(): BotProfile;

    /**
     * Confere um token antes de salvar.
     *
     * @throws TokenRefused quando o Telegram recusa o token
     * @throws TelegramUnavailable quando o Telegram não responde
     */
    public function meWith(string $token): BotProfile;

    /**
     * Nome, descrições e o menu de comandos que o Telegram mostra ao digitar "/".
     *
     * @param  array<string, string>  $commands  comando sem a barra => descrição
     */
    public function brand(string $name, string $shortDescription, string $description, array $commands): void;

    /**
     * Long polling: espera até 30 s por mensagens novas a partir do offset.
     *
     * @return list<IncomingMessage>
     */
    public function updates(?int $offset): array;

    public function send(int $chatId, string $html): void;
}

<?php

namespace App\Modules\Telegram\Application;

/** Bot API do Telegram, só o que o app usa. Erros viram TelegramUnavailable. */
interface TelegramApi
{
    /** Há token no `.env`. Sem ele, nada é pedido ao Telegram. */
    public function isConfigured(): bool;

    public function me(): BotProfile;

    /**
     * Long polling: espera até 30 s por mensagens novas a partir do offset.
     *
     * @return list<IncomingMessage>
     */
    public function updates(?int $offset): array;

    public function send(int $chatId, string $html): void;
}

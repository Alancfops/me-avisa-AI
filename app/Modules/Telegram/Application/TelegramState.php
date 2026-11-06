<?php

namespace App\Modules\Telegram\Application;

use DateTimeImmutable;

/** Token salvo pela aba, chat conectado, código de conexão pendente e a última mensagem lida. */
interface TelegramState
{
    public function chatId(): ?int;

    public function connectedAt(): ?DateTimeImmutable;

    public function connect(int $chatId, DateTimeImmutable $at): void;

    public function disconnect(): void;

    public function linkCode(): ?string;

    public function linkCodeExpiresAt(): ?DateTimeImmutable;

    public function saveLinkCode(string $code, DateTimeImmutable $expiresAt): void;

    public function clearLinkCode(): void;

    public function lastUpdateId(): ?int;

    public function saveLastUpdateId(int $id): void;

    public function token(): ?string;

    /** Bot novo, conversa nova: apaga chat, código e último update. */
    public function saveToken(string $token): void;

    /** Sem bot não há conexão: apaga o token, o chat e o código. */
    public function forgetToken(): void;
}

<?php

namespace App\Modules\Telegram\Application;

use DateTimeImmutable;

/** Chat conectado, código de conexão pendente e a última mensagem lida. */
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
}

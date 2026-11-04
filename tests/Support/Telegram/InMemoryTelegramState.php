<?php

namespace Tests\Support\Telegram;

use App\Modules\Telegram\Application\TelegramState;
use DateTimeImmutable;

final class InMemoryTelegramState implements TelegramState
{
    public ?int $chat = null;

    public ?DateTimeImmutable $connected = null;

    public ?string $code = null;

    public ?DateTimeImmutable $codeExpires = null;

    public ?int $lastUpdate = null;

    public ?string $savedToken = null;

    public function token(): ?string
    {
        return $this->savedToken;
    }

    public function saveToken(string $token): void
    {
        $this->savedToken = $token;
        $this->disconnect();
        $this->clearLinkCode();
        $this->lastUpdate = null;
    }

    public function forgetToken(): void
    {
        $this->savedToken = null;
    }

    public function chatId(): ?int
    {
        return $this->chat;
    }

    public function connectedAt(): ?DateTimeImmutable
    {
        return $this->connected;
    }

    public function connect(int $chatId, DateTimeImmutable $at): void
    {
        [$this->chat, $this->connected] = [$chatId, $at];
    }

    public function disconnect(): void
    {
        [$this->chat, $this->connected] = [null, null];
    }

    public function linkCode(): ?string
    {
        return $this->code;
    }

    public function linkCodeExpiresAt(): ?DateTimeImmutable
    {
        return $this->codeExpires;
    }

    public function saveLinkCode(string $code, DateTimeImmutable $expiresAt): void
    {
        [$this->code, $this->codeExpires] = [$code, $expiresAt];
    }

    public function clearLinkCode(): void
    {
        [$this->code, $this->codeExpires] = [null, null];
    }

    public function lastUpdateId(): ?int
    {
        return $this->lastUpdate;
    }

    public function saveLastUpdateId(int $id): void
    {
        $this->lastUpdate = $id;
    }
}

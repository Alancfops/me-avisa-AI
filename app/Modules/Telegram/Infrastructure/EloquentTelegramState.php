<?php

namespace App\Modules\Telegram\Infrastructure;

use App\Modules\Telegram\Application\TelegramState;
use DateTimeImmutable;

/** Uma linha só: há um chat por instalação. */
final class EloquentTelegramState implements TelegramState
{
    public function chatId(): ?int
    {
        return $this->row()->chat_id;
    }

    public function connectedAt(): ?DateTimeImmutable
    {
        return $this->row()->connected_at?->toDateTimeImmutable();
    }

    public function connect(int $chatId, DateTimeImmutable $at): void
    {
        $this->row()->update(['chat_id' => $chatId, 'connected_at' => $at->format(DATE_ATOM)]);
    }

    public function disconnect(): void
    {
        $this->row()->update(['chat_id' => null, 'connected_at' => null]);
    }

    public function linkCode(): ?string
    {
        return $this->row()->link_code;
    }

    public function linkCodeExpiresAt(): ?DateTimeImmutable
    {
        return $this->row()->link_code_expires_at?->toDateTimeImmutable();
    }

    public function saveLinkCode(string $code, DateTimeImmutable $expiresAt): void
    {
        $this->row()->update(['link_code' => $code, 'link_code_expires_at' => $expiresAt->format(DATE_ATOM)]);
    }

    public function clearLinkCode(): void
    {
        $this->row()->update(['link_code' => null, 'link_code_expires_at' => null]);
    }

    public function lastUpdateId(): ?int
    {
        return $this->row()->last_update_id;
    }

    public function saveLastUpdateId(int $id): void
    {
        $this->row()->update(['last_update_id' => $id]);
    }

    public function token(): ?string
    {
        return $this->row()->token;
    }

    public function saveToken(string $token): void
    {
        $this->row()->update([
            'token' => $token, 'chat_id' => null, 'connected_at' => null,
            'link_code' => null, 'link_code_expires_at' => null, 'last_update_id' => null,
        ]);
    }

    public function forgetToken(): void
    {
        $this->row()->update([
            'token' => null, 'chat_id' => null, 'connected_at' => null,
            'link_code' => null, 'link_code_expires_at' => null,
        ]);
    }

    private function row(): TelegramStateModel
    {
        return TelegramStateModel::query()->orderBy('id')->firstOrCreate([]);
    }
}

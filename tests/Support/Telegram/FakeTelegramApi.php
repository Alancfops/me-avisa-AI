<?php

namespace Tests\Support\Telegram;

use App\Modules\Telegram\Application\BotProfile;
use App\Modules\Telegram\Application\IncomingMessage;
use App\Modules\Telegram\Application\TelegramApi;
use App\Modules\Telegram\Application\TelegramUnavailable;

final class FakeTelegramApi implements TelegramApi
{
    /** @var list<array{int, string}> chat id e texto, na ordem enviada */
    public array $sent = [];

    /** @var list<?int> offsets pedidos */
    public array $offsets = [];

    /** @param list<IncomingMessage> $pending */
    public function __construct(
        public array $pending = [],
        public bool $configured = true,
        public bool $down = false,
    ) {}

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function me(): BotProfile
    {
        $this->failIfDown();

        return new BotProfile('meavisa_bot', 'Me Avisa');
    }

    public function updates(?int $offset): array
    {
        $this->failIfDown();
        $this->offsets[] = $offset;

        return array_values(array_filter($this->pending, fn (IncomingMessage $m) => $offset === null || $m->updateId >= $offset));
    }

    public function send(int $chatId, string $html): void
    {
        $this->failIfDown();
        $this->sent[] = [$chatId, $html];
    }

    private function failIfDown(): void
    {
        if ($this->down) {
            throw new TelegramUnavailable('Não conseguimos falar com o Telegram.');
        }
    }
}

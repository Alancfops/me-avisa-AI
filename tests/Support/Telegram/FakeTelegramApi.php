<?php

namespace Tests\Support\Telegram;

use App\Modules\Telegram\Application\BotProfile;
use App\Modules\Telegram\Application\IncomingMessage;
use App\Modules\Telegram\Application\TelegramApi;
use App\Modules\Telegram\Application\TelegramUnavailable;
use App\Modules\Telegram\Application\TokenRefused;

final class FakeTelegramApi implements TelegramApi
{
    /** @var list<array{int, string}> chat id e texto, na ordem enviada */
    public array $sent = [];

    /** @var list<?int> offsets pedidos */
    public array $offsets = [];

    /** @var list<string> tokens conferidos com meWith */
    public array $checked = [];

    /** @var list<array{name: string, short: string, description: string, commands: array<string, string>}> */
    public array $branded = [];

    /** @param list<IncomingMessage> $pending */
    public function __construct(
        public array $pending = [],
        public bool $configured = true,
        public bool $down = false,
        public bool $refused = false,
        public ?string $source = null,
    ) {}

    public function tokenSource(): ?string
    {
        return $this->configured ? ($this->source ?? 'env') : null;
    }

    public function meWith(string $token): BotProfile
    {
        $this->checked[] = $token;
        $this->failIfDown();

        return new BotProfile('novo_bot', 'Novo');
    }

    public function brand(string $name, string $shortDescription, string $description, array $commands): void
    {
        $this->failIfDown();
        $this->branded[] = ['name' => $name, 'short' => $shortDescription, 'description' => $description, 'commands' => $commands];
    }

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
        if ($this->refused) {
            throw new TokenRefused('O Telegram recusou o token do bot.');
        }

        if ($this->down) {
            throw new TelegramUnavailable('Não conseguimos falar com o Telegram.');
        }
    }
}

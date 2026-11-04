<?php

namespace App\Modules\Telegram\Application;

use App\Modules\Fiis\Application\Alert;
use App\Modules\Fiis\Application\Clock;
use App\Modules\Fiis\Application\DescribeFund;
use App\Modules\Fiis\Application\ListAlerts;
use App\Modules\Fiis\Application\ListFavorites;
use App\Modules\Fiis\Application\MarketTime;
use App\Modules\Fiis\Application\ShowPortfolio;
use Psr\Log\LoggerInterface;

/**
 * Responde a uma mensagem recebida. Sem chat conectado, só `/start <código>` faz algo;
 * conectado, mensagens de outros chats são ignoradas sem resposta.
 */
final readonly class HandleMessage
{
    public function __construct(
        private TelegramApi $api,
        private TelegramState $state,
        private Messages $messages,
        private ListAlerts $alerts,
        private DescribeFund $describe,
        private ListFavorites $favorites,
        private ShowPortfolio $portfolio,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    public function handle(IncomingMessage $message): void
    {
        if ($message->chatId === 0) {
            return;
        }

        [$command, $argument] = self::parse($message->text);
        $connected = $this->state->chatId();

        if ($connected === null) {
            $this->connectOrExplain($message->chatId, $command, $argument);

            return;
        }

        if ($connected !== $message->chatId) {
            $this->logger->info("Mensagem de um chat não conectado ignorada ({$message->chatId}).");

            return;
        }

        $this->api->send($message->chatId, $this->answer($command, $argument));
    }

    private function connectOrExplain(int $chatId, string $command, string $argument): void
    {
        $code = $this->state->linkCode();
        $expiresAt = $this->state->linkCodeExpiresAt();
        $valid = $command === 'start' && $code !== null && $expiresAt !== null
            && strtoupper($argument) === $code && $this->clock->now() < $expiresAt;

        if (! $valid) {
            $this->api->send($chatId, $this->messages->howToConnect());

            return;
        }

        $this->state->connect($chatId, $this->clock->now());
        $this->state->clearLinkCode();
        $this->api->send($chatId, $this->messages->welcome($this->botName()));
    }

    private function answer(string $command, string $argument): string
    {
        return match ($command) {
            'alertas' => $this->messages->todayAlerts($this->todayAlerts(), $this->today()),
            'ver' => $this->fund($argument),
            'favoritos' => $this->messages->favorites($this->favorites->handle(), $this->alertedToday()),
            'carteira' => $this->messages->portfolio($this->portfolio->handle()),
            default => $this->messages->help(),
        };
    }

    private function fund(string $argument): string
    {
        $ticker = strtoupper(trim($argument));

        if ($ticker === '') {
            return $this->messages->verUsage();
        }

        $view = $this->describe->handle($ticker, detailed: true);

        return $view === null ? $this->messages->notFound($ticker) : $this->messages->fund($view);
    }

    /** @return list<Alert> */
    private function todayAlerts(): array
    {
        $today = $this->today();

        return array_values(array_filter(
            $this->alerts->handle(),
            fn (Alert $alert): bool => MarketTime::day($alert->occurredAt) === $today,
        ));
    }

    /** @return list<string> */
    private function alertedToday(): array
    {
        return array_values(array_unique(array_map(fn (Alert $alert): string => $alert->ticker, $this->todayAlerts())));
    }

    private function today(): string
    {
        return MarketTime::day($this->clock->now());
    }

    private function botName(): string
    {
        try {
            return $this->api->me()->name;
        } catch (TelegramUnavailable) {
            return 'Me Avisa Aí';
        }
    }

    /**
     * "/VER@meu_bot knri11" vira ['ver', 'knri11']; texto sem barra não é comando.
     *
     * @return array{string, string}
     */
    private static function parse(string $text): array
    {
        $text = trim($text);

        if (! str_starts_with($text, '/')) {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $text, 2) ?: [''];
        $command = strtolower(explode('@', substr($parts[0], 1))[0]);

        return [$command, trim($parts[1] ?? '')];
    }
}

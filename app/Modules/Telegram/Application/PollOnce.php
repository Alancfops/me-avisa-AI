<?php

namespace App\Modules\Telegram\Application;

use App\Modules\Fiis\Application\Source;
use App\Modules\Fiis\Application\SourceLog;
use Psr\Log\LoggerInterface;
use Throwable;

/** Uma rodada de long polling: lê, responde e guarda o último update tratado. */
final readonly class PollOnce
{
    public function __construct(
        private TelegramApi $api,
        private TelegramState $state,
        private MessageHandler $handler,
        private SourceLog $log,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return int quantas mensagens tratou
     *
     * @throws TelegramUnavailable quando o Telegram não responde; quem chama espera e tenta de novo
     */
    public function handle(): int
    {
        if (! $this->api->isConfigured()) {
            return 0;
        }

        $last = $this->state->lastUpdateId();

        try {
            $messages = $this->api->updates($last === null ? null : $last + 1);
        } catch (TelegramUnavailable $failure) {
            $this->log->failure(Source::Telegram, $failure->getMessage());

            throw $failure;
        }

        $handled = 0;

        foreach ($messages as $message) {
            // O Telegram reenvia o que não foi confirmado; o que já foi tratado não responde de novo.
            if ($last !== null && $message->updateId <= $last) {
                continue;
            }

            try {
                $this->handler->handle($message);
            } catch (Throwable $failure) {
                $this->logger->error("Mensagem {$message->updateId} do Telegram falhou: ".$failure::class);
            }

            $this->state->saveLastUpdateId($message->updateId);
            $last = $message->updateId;
            $handled++;
        }

        return $handled;
    }
}

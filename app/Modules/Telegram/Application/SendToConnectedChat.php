<?php

namespace App\Modules\Telegram\Application;

use App\Modules\Fiis\Application\Source;
use App\Modules\Fiis\Application\SourceLog;

/** Envia ao chat conectado e registra o resultado na Saúde. */
final readonly class SendToConnectedChat
{
    public function __construct(
        private TelegramApi $api,
        private TelegramState $state,
        private SourceLog $log,
    ) {}

    /**
     * @return bool `false` se não há chat conectado
     *
     * @throws TelegramUnavailable para a fila tentar de novo
     */
    public function handle(string $html): bool
    {
        $chatId = $this->state->chatId();

        if ($chatId === null) {
            return false;
        }

        try {
            $this->api->send($chatId, $html);
        } catch (TelegramUnavailable $failure) {
            $this->log->failure(Source::Telegram, $failure->getMessage());

            throw $failure;
        }

        $this->log->success(Source::Telegram);

        return true;
    }
}

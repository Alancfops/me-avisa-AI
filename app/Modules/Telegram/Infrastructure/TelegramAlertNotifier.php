<?php

namespace App\Modules\Telegram\Infrastructure;

use App\Modules\Fiis\Application\Alert;
use App\Modules\Fiis\Application\AlertNotifier;
use App\Modules\Fiis\Application\DescribeFund;
use App\Modules\Telegram\Application\Messages;
use App\Modules\Telegram\Application\TelegramApi;
use App\Modules\Telegram\Application\TelegramState;

/** Todo aviso gravado vai para o chat conectado, pela fila. */
final readonly class TelegramAlertNotifier implements AlertNotifier
{
    public function __construct(
        private TelegramApi $api,
        private TelegramState $state,
        private DescribeFund $describe,
        private Messages $messages,
    ) {}

    public function notify(Alert $alert): void
    {
        if (! $this->api->isConfigured() || $this->state->chatId() === null) {
            return;
        }

        SendTelegramMessageJob::dispatch($this->messages->alert($alert, $this->describe->handle($alert->ticker)));
    }
}

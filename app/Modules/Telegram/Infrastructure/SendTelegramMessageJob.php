<?php

namespace App\Modules\Telegram\Infrastructure;

use App\Modules\Telegram\Application\SendToConnectedChat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** O aviso já está gravado; aqui só a entrega, com novas tentativas se o Telegram falhar. */
final class SendTelegramMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> Segundos entre as tentativas: aguenta alguns minutos sem internet. */
    public array $backoff = [60, 300];

    public function __construct(public string $html) {}

    public function handle(SendToConnectedChat $send): void
    {
        $send->handle($this->html);
    }
}

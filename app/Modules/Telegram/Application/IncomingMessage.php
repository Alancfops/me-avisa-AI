<?php

namespace App\Modules\Telegram\Application;

final readonly class IncomingMessage
{
    public function __construct(
        public int $updateId,
        public int $chatId,
        /** Vazio para o que não é texto (figurinha, foto): o offset avança do mesmo jeito. */
        public string $text,
    ) {}
}

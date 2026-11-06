<?php

namespace App\Modules\Telegram\Application;

final readonly class Disconnect
{
    public function __construct(private TelegramState $state) {}

    public function handle(): void
    {
        $this->state->disconnect();
    }
}

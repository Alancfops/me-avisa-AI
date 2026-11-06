<?php

namespace App\Modules\Telegram\Application;

final readonly class BotProfile
{
    public function __construct(
        public string $username,
        public string $name,
    ) {}
}

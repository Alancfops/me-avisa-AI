<?php

namespace App\Modules\Telegram\Application;

interface MessageHandler
{
    public function handle(IncomingMessage $message): void;
}

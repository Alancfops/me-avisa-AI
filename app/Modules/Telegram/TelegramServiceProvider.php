<?php

namespace App\Modules\Telegram;

use App\Modules\Telegram\Application\TelegramApi;
use App\Modules\Telegram\Application\TelegramState;
use App\Modules\Telegram\Infrastructure\EloquentTelegramState;
use App\Modules\Telegram\Infrastructure\HttpTelegramApi;
use Illuminate\Support\ServiceProvider;

final class TelegramServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        TelegramApi::class => HttpTelegramApi::class,
        TelegramState::class => EloquentTelegramState::class,
    ];
}

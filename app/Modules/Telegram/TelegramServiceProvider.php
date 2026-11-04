<?php

namespace App\Modules\Telegram;

use App\Modules\Fiis\Application\AlertNotifier;
use App\Modules\Telegram\Application\HandleMessage;
use App\Modules\Telegram\Application\MessageHandler;
use App\Modules\Telegram\Application\Messages;
use App\Modules\Telegram\Application\TelegramApi;
use App\Modules\Telegram\Application\TelegramState;
use App\Modules\Telegram\Infrastructure\Console\PollCommand;
use App\Modules\Telegram\Infrastructure\EloquentTelegramState;
use App\Modules\Telegram\Infrastructure\HttpTelegramApi;
use App\Modules\Telegram\Infrastructure\TelegramAlertNotifier;
use Illuminate\Support\ServiceProvider;

final class TelegramServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        // Sobrescreve o NullAlertNotifier do módulo de FIIs: os avisos passam a ir para o Telegram.
        AlertNotifier::class => TelegramAlertNotifier::class,
        MessageHandler::class => HandleMessage::class,
        TelegramApi::class => HttpTelegramApi::class,
        TelegramState::class => EloquentTelegramState::class,
    ];

    public function register(): void
    {
        $this->app->bind(Messages::class, fn (): Messages => new Messages((string) config('app.url')));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PollCommand::class]);
        }
    }
}

<?php

namespace App\Modules\Telegram\Infrastructure\Console;

use App\Modules\Telegram\Application\PollOnce;
use App\Modules\Telegram\Application\SetUpBot;
use App\Modules\Telegram\Application\TelegramApi;
use App\Modules\Telegram\Application\TelegramUnavailable;
use Illuminate\Console\Command;

/** Fica ouvindo o Telegram (serviço `bot` do Compose). Sem webhook: o app roda só em localhost. */
final class PollCommand extends Command
{
    protected $signature = 'telegram:poll {--once : Faz uma rodada e sai}';

    protected $description = 'Lê as mensagens do bot do Telegram e responde';

    /**
     * Sem token, confere de novo de tempos em tempos: sair faria o Compose reiniciar em laço.
     * Curto para o bot começar logo depois de a pessoa colar o token na aba.
     */
    private const IDLE_SECONDS = 5;

    private const RETRY_SECONDS = 10;

    private bool $running = true;

    public function handle(PollOnce $poll, TelegramApi $api, SetUpBot $setUp): int
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn () => $this->running = false);
            pcntl_signal(SIGINT, fn () => $this->running = false);
        }

        $setUpDone = false;

        do {
            $wait = 0;

            if (! $setUpDone && $api->isConfigured()) {
                $setUp->handle();
                $setUpDone = true;
            }

            try {
                if (! $api->isConfigured()) {
                    $wait = self::IDLE_SECONDS;
                } else {
                    $poll->handle();
                }
            } catch (TelegramUnavailable $failure) {
                $this->warn($failure->getMessage());
                $wait = self::RETRY_SECONDS;
            }

            if ($this->option('once')) {
                break;
            }

            $this->sleepWhileRunning($wait);
        } while ($this->running);

        return self::SUCCESS;
    }

    private function sleepWhileRunning(int $seconds): void
    {
        for ($i = 0; $i < $seconds && $this->running; $i++) {
            sleep(1);
        }
    }
}

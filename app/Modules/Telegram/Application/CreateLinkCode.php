<?php

namespace App\Modules\Telegram\Application;

use App\Modules\Fiis\Application\Clock;

/** Código que a aba Telegram mostra e o bot recebe em `/start <código>`. */
final readonly class CreateLinkCode
{
    /** Sem 0/O, 1/I/L: o código é lido na tela e digitado. */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LENGTH = 6;

    public const VALID_MINUTES = 15;

    public function __construct(
        private TelegramState $state,
        private Clock $clock,
    ) {}

    public function handle(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        $this->state->saveLinkCode($code, $this->clock->now()->modify('+'.self::VALID_MINUTES.' minutes'));

        return $code;
    }
}

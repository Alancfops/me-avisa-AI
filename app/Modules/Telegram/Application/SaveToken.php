<?php

namespace App\Modules\Telegram\Application;

/** Token colado na aba Telegram: confere com o Telegram, guarda e ajusta o perfil do bot. */
final readonly class SaveToken
{
    /** Formato do @BotFather: número do bot, dois-pontos e a chave. */
    private const FORMAT = '/^\d{5,}:[A-Za-z0-9_-]{30,}$/';

    public function __construct(private TelegramApi $api, private TelegramState $state, private SetUpBot $setUp) {}

    /**
     * @throws InvalidTokenInput quando o texto não é um token ou o .env já define um
     * @throws TelegramUnavailable quando o Telegram recusa o token ou não responde
     */
    public function handle(string $token): BotProfile
    {
        $token = trim($token);

        if ($this->api->tokenSource() === 'env') {
            throw new InvalidTokenInput('O token já vem do arquivo .env. Para usar outro, troque ou apague a linha TELEGRAM_BOT_TOKEN.');
        }

        if (preg_match(self::FORMAT, $token) !== 1) {
            throw new InvalidTokenInput('Isso não parece um token. Copie a linha inteira que o @BotFather mandou, como 123456789:AAH...');
        }

        $bot = $this->api->meWith($token);

        if ($this->state->token() !== $token) {
            $this->state->saveToken($token);
        }

        $this->setUp->handle();

        return $bot;
    }
}

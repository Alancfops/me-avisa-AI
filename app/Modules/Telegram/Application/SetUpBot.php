<?php

namespace App\Modules\Telegram\Application;

use Psr\Log\LoggerInterface;

/** Deixa todo bot igual: mesmo nome, mesmas descrições e o menu de comandos. */
final readonly class SetUpBot
{
    public const NAME = 'Me Avisa Aí';

    private const SHORT_DESCRIPTION = 'Avisos dos seus FIIs favoritos, enviados pelo app Me Avisa Aí no seu computador.';

    private const DESCRIPTION = "🔔 Avisa quando um FII favorito bate uma regra de preço e responde a consultas rápidas.\n\n"
        .'Este bot conversa só com o app Me Avisa Aí instalado no seu computador. Para conectar, '
        .'abra a aba Telegram do app e toque em Abrir no Telegram, ou leia o QR code com o celular.';

    public function __construct(private TelegramApi $api, private LoggerInterface $logger) {}

    /** Não é essencial: se falhar, o bot funciona igual e a próxima partida tenta de novo. */
    public function handle(): void
    {
        $commands = [];

        foreach (BotCommands::LIST as $item) {
            $commands[$item['command']] = $item['description'];
        }

        try {
            $this->api->brand(self::NAME, self::SHORT_DESCRIPTION, self::DESCRIPTION, $commands);
        } catch (TelegramUnavailable $failure) {
            $this->logger->warning('Não deu para ajustar o perfil do bot: '.$failure->getMessage());
        }
    }
}

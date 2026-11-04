<?php

namespace App\Modules\Telegram\Application;

/** Os comandos do bot: a aba Telegram lista e o menu "/" do Telegram mostra. */
final class BotCommands
{
    /** @var list<array{command: string, example: string, description: string}> */
    public const LIST = [
        ['command' => 'alertas', 'example' => '/alertas', 'description' => 'Favoritos que bateram regra hoje'],
        ['command' => 'ver', 'example' => '/ver CÓDIGO', 'description' => 'Preço, P/VP e rendimento de um fundo'],
        ['command' => 'favoritos', 'example' => '/favoritos', 'description' => 'Seus favoritos com o preço'],
        ['command' => 'carteira', 'example' => '/carteira', 'description' => 'Valor e rendimento estimado da carteira'],
        ['command' => 'ajuda', 'example' => '/ajuda', 'description' => 'A lista de comandos'],
    ];
}

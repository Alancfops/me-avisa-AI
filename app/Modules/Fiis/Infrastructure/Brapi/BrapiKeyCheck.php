<?php

namespace App\Modules\Fiis\Infrastructure\Brapi;

use App\Modules\Fiis\Application\BrapiKeyChecker;

/** Pergunta o consumo da conta: só responde com uma chave válida. */
final readonly class BrapiKeyCheck implements BrapiKeyChecker
{
    public function __construct(private BrapiClient $client) {}

    public function check(string $token): void
    {
        $this->client->get('/api/v2/user/usage', [], $token);
    }
}

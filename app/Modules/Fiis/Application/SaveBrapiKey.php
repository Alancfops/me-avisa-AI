<?php

namespace App\Modules\Fiis\Application;

/** Chave colada em Configurações: confere com a brapi e guarda. */
final readonly class SaveBrapiKey
{
    private const FORMAT = '/^[A-Za-z0-9_-]{10,200}$/';

    public function __construct(private BrapiCredentials $credentials, private BrapiKeyChecker $checker) {}

    /**
     * @throws InvalidBrapiKey quando o texto não é uma chave ou o .env já define uma
     * @throws SourceUnavailable quando a brapi recusa a chave ou não responde
     */
    public function handle(string $token): void
    {
        $token = trim($token);

        if ($this->credentials->source() === 'env') {
            throw new InvalidBrapiKey('A chave já vem do arquivo .env. Para usar outra, troque ou apague a linha BRAPI_TOKEN.');
        }

        if (preg_match(self::FORMAT, $token) !== 1) {
            throw new InvalidBrapiKey('Isso não parece uma chave da brapi. Copie o token inteiro do painel da brapi, sem espaços.');
        }

        $this->checker->check($token);
        $this->credentials->save($token);
    }
}

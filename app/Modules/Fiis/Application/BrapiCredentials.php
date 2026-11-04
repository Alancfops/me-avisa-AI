<?php

namespace App\Modules\Fiis\Application;

/** A chave da brapi: a do `.env` tem prioridade; senão, a colada em Configurações. */
interface BrapiCredentials
{
    public function token(): ?string;

    /** `env`, `app` ou null sem chave. */
    public function source(): ?string;

    public function save(string $token): void;

    public function forget(): void;
}

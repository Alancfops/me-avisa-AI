<?php

namespace App\Modules\Fiis\Application;

interface BrapiKeyChecker
{
    /**
     * Confere uma chave antes de salvar.
     *
     * @throws BrapiKeyRefused quando a brapi recusa a chave
     * @throws SourceUnavailable quando a brapi não responde
     */
    public function check(string $token): void;
}

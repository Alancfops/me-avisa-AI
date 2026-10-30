<?php

namespace App\Modules\Fiis\Application;

interface SourceLog
{
    public function success(Source $source): void;

    /** A mensagem aparece na tela: em português simples, sem token nem corpo de resposta. */
    public function failure(Source $source, string $message): void;

    public function status(Source $source): SourceStatus;
}

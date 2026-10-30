<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\DailyClose;

interface Quotes
{
    /** @throws SourceUnavailable */
    public function latest(string $ticker): Quote;

    /**
     * Inclui a barra do pregão aberto, se a fonte a enviar: quem chama decide se ela
     * já é um fechamento.
     *
     * @return list<DailyClose>
     *
     * @throws SourceUnavailable
     */
    public function history(string $ticker): array;
}

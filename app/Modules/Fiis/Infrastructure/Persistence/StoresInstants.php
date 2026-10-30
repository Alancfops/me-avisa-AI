<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

/**
 * O formato padrão do Laravel descarta o fuso ao gravar, e `timestamptz` assume o
 * fuso da sessão: 15:00-03:00 viraria 15:00 UTC. Isso moveria o teto de 24 h e o
 * horário da cotação, então estes models gravam o instante com o deslocamento.
 */
trait StoresInstants
{
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:sP';
    }
}

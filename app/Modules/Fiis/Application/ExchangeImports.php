<?php

namespace App\Modules\Fiis\Application;

/**
 * Dias cujo arquivo da B3 já foi lido. Não dá para deduzir pelos fechamentos gravados:
 * a brapi grava o dia dos favoritos antes da B3, e o dia pareceria feito.
 */
interface ExchangeImports
{
    public function has(string $day): bool;

    public function mark(string $day): void;
}

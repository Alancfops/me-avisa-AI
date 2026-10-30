<?php

namespace App\Modules\Fiis\Domain;

final class Isin
{
    /** Cota de FII negocia com a raiz do ISIN (posições 3–6) + 11: `BRMXRFCTF008` → `MXRF11`. */
    public static function tickerOf(string $isin): ?string
    {
        $isin = strtoupper(trim($isin));

        return preg_match('/^[A-Z0-9]{12}$/D', $isin) === 1 ? substr($isin, 2, 4).'11' : null;
    }
}

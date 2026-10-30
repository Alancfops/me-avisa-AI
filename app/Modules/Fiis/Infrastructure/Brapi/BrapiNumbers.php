<?php

namespace App\Modules\Fiis\Infrastructure\Brapi;

use Brick\Math\BigDecimal;

final class BrapiNumbers
{
    /**
     * O JSON já chega como float, então parte da precisão depende daqui: `json_encode`
     * devolve a menor forma que reproduz o número, `-3.0` e não `-3.0000000000000004`,
     * enquanto um cast para string cortaria em 14 dígitos.
     *
     * @return string|null `null` se o valor não for numérico; texto não conta como número
     */
    public static function decimal(mixed $value): ?string
    {
        if (! is_int($value) && ! is_float($value)) {
            return null;
        }

        return (string) BigDecimal::of((string) json_encode($value));
    }

    public static function normalizeTicker(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $ticker = strtoupper(trim($value));

        return preg_match('/^[A-Z0-9]{4,12}$/D', $ticker) === 1 ? $ticker : null;
    }
}

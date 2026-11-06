<?php

namespace App\Modules\Fiis\Domain;

final readonly class WindowAverage
{
    public function __construct(
        public int $months,
        /** Meses inteiros de histórico guardado, no máximo `$months`. */
        public int $monthsStored,
        /** `null` enquanto o histórico não cobre a janela inteira. */
        public ?Money $average,
    ) {}
}

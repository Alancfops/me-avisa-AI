<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\DailyClose;

interface MarketData
{
    public function latestQuote(string $ticker): ?Quote;

    /** Ignora cotação mais velha que a guardada: fonte atrasada não apaga dado novo. */
    public function saveQuote(Quote $quote): void;

    /** @param list<DailyClose> $closes */
    public function saveCloses(string $ticker, array $closes): void;

    /**
     * Fechamentos em `$fromDay ≤ dia < $day`, do mais antigo ao mais novo.
     *
     * @return list<DailyClose>
     */
    public function closesBefore(string $ticker, string $day, string $fromDay): array;
}

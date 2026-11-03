<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\DailyClose;

interface MarketData
{
    public function latestQuote(string $ticker): ?Quote;

    /** Ignora cotação mais velha que a guardada: fonte atrasada não apaga dado novo. */
    public function saveQuote(Quote $quote): void;

    public function deleteQuote(string $ticker): void;

    /** @param list<DailyClose> $closes */
    public function saveCloses(string $ticker, array $closes): void;

    /** Fechamento mais recente do ticker, de qualquer idade: a página mostra o dia dele. */
    public function latestClose(string $ticker): ?DailyClose;

    /**
     * Último fechamento de cada ticker que negociou nos últimos `$tradingDays` dias úteis,
     * contados a partir do pregão mais recente guardado (e não do relógio: com a máquina
     * parada, a lista mostra o último dia que o app conhece, em vez de ficar vazia).
     *
     * @return array<string, DailyClose>
     */
    public function latestCloses(int $tradingDays = 10): array;

    /**
     * Fechamentos em `$fromDay ≤ dia < $day`, do mais antigo ao mais novo.
     *
     * @return list<DailyClose>
     */
    public function closesBefore(string $ticker, string $day, string $fromDay): array;
}

<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\BookValueReport;
use App\Modules\Fiis\Domain\MonthlyYield;

interface ReportRepository
{
    /**
     * Vazia se mais de um fundo declara o ticker: sem saber qual é o certo, o VP de
     * um fundo poderia disparar a regra de outro.
     *
     * @return list<BookValueReport>
     */
    public function bookValues(string $ticker): array;

    /**
     * Vazia na mesma situação de `bookValues`.
     *
     * @return list<MonthlyYield>
     */
    public function monthlyYields(string $ticker): array;

    /**
     * Não troca um informe por uma versão anterior.
     *
     * @param  list<StoredReport>  $reports
     */
    public function save(array $reports): void;

    /** @return list<string> tickers declarados por mais de um fundo */
    public function ambiguousTickers(): array;
}

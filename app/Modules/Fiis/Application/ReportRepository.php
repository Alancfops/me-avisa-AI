<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\BookValueReport;
use App\Modules\Fiis\Domain\FundKind;
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
     * Informe de competência mais recente com algum campo de perfil. `null` na mesma
     * situação de `bookValues`, ou sem informe.
     */
    public function profile(string $ticker): ?FundProfile;

    /**
     * Tipo de cada ticker pela composição do informe mais recente que a tem. Ticker
     * declarado por mais de um fundo fica de fora, como no VP.
     *
     * @return array<string, FundKind>
     */
    public function kinds(): array;

    /** `null` na mesma situação de `kinds`, ou sem composição. */
    public function kindOf(string $ticker): ?FundKind;

    /**
     * Não troca um informe por uma versão anterior.
     *
     * @param  list<StoredReport>  $reports
     */
    public function save(array $reports): void;

    /** @return list<string> tickers declarados por mais de um fundo */
    public function ambiguousTickers(): array;
}

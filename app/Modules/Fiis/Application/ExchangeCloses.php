<?php

namespace App\Modules\Fiis\Application;

/** Fechamentos de todos os FIIs negociados, vindos da bolsa (B3). */
interface ExchangeCloses
{
    /**
     * Devolve vazio se o arquivo do dia ainda não existe (fim de semana, feriado ou antes
     * do fechamento). A falha de rede ou o arquivo quebrado aparece ao percorrer o resultado.
     *
     * @return iterable<ExchangeClose>
     *
     * @throws SourceUnavailable
     */
    public function forDay(string $day): iterable;

    /**
     * Em stream: o arquivo anual não cabe na memória de uma vez.
     *
     * @return iterable<ExchangeClose>
     *
     * @throws SourceUnavailable
     */
    public function forYear(int $year): iterable;
}

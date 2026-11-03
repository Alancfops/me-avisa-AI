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

    /** Dia mais recente com arquivo lido, ou null se nenhum: é o que a lista pode citar como fonte B3. */
    public function latestDay(): ?string;

    /** Arquivo anual lido por inteiro (a primeira carga de 12 meses). */
    public function hasYear(int $year): bool;

    public function markYear(int $year): void;
}

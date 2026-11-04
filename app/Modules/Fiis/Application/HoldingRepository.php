<?php

namespace App\Modules\Fiis\Application;

/** Cotas de cada FII da carteira. Um ticker aparece uma vez. */
interface HoldingRepository
{
    /** @return array<string, int> ticker => cotas, em ordem de ticker */
    public function all(): array;

    public function shares(string $ticker): ?int;

    /** Cria ou atualiza a quantidade. */
    public function save(string $ticker, int $shares): void;

    /** @return bool `false` se o ticker não estava na carteira */
    public function remove(string $ticker): bool;
}

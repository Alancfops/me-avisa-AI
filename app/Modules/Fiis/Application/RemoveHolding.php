<?php

namespace App\Modules\Fiis\Application;

final readonly class RemoveHolding
{
    public function __construct(private HoldingRepository $holdings) {}

    /** @return bool `false` se o ticker não estava na carteira */
    public function handle(string $ticker): bool
    {
        return $this->holdings->remove($ticker);
    }
}

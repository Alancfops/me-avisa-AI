<?php

namespace App\Modules\Fiis\Application;

final readonly class SaveHolding
{
    public function __construct(private HoldingRepository $holdings) {}

    public function handle(string $ticker, int $shares): void
    {
        $this->holdings->save($ticker, $shares);
    }
}

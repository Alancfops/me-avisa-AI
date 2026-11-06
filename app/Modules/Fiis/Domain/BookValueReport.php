<?php

namespace App\Modules\Fiis\Domain;

final readonly class BookValueReport
{
    public function __construct(
        /** Competência em `Y-m`. */
        public string $month,
        /** `Y-m-d` da entrega à CVM: antes disso, o número não era público. */
        public string $deliveredOn,
        public Money $bookValue,
    ) {}
}

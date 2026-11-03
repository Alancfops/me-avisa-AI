<?php

namespace App\Modules\Fiis\Application;

/** Dados cadastrais do fundo no informe mensal da CVM mais recente que os traz. */
final readonly class FundProfile
{
    public function __construct(
        /** Competência em `Y-m`. */
        public string $month,
        public ?string $segment,
        public ?string $administrator,
        /** `Y-m-d`. */
        public ?string $startedOn,
        /** Decimal em reais. */
        public ?string $netAssets,
        public ?int $holders,
    ) {}
}

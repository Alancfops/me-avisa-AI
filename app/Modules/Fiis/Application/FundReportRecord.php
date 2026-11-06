<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\Portfolio;

final readonly class FundReportRecord
{
    public function __construct(
        public string $cnpj,
        public string $isin,
        /** Competência em `Y-m`. */
        public string $month,
        public int $version,
        public string $deliveredOn,
        public ?string $bookValue,
        public ?string $dividendYield,
        public ?string $segment = null,
        public ?string $administrator = null,
        /** Data de início do fundo, em `Y-m-d`. */
        public ?string $startedOn = null,
        public ?string $netAssets = null,
        public ?int $holders = null,
        public ?Portfolio $portfolio = null,
    ) {}

    public function withPortfolio(?Portfolio $portfolio): self
    {
        return new self(
            $this->cnpj, $this->isin, $this->month, $this->version, $this->deliveredOn, $this->bookValue, $this->dividendYield,
            $this->segment, $this->administrator, $this->startedOn, $this->netAssets, $this->holders, $portfolio,
        );
    }
}

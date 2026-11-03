<?php

namespace App\Modules\Fiis\Domain;

use Brick\Math\BigDecimal;

/** Composição da carteira no informe da CVM (docs/superpowers/specs/2026-11-03-lista-de-fiis-design.md, "Tipo do fundo"). */
final readonly class Portfolio
{
    public function __construct(
        public string $totalInvested,
        public string $realEstate,
        /** CRI, CRI_CRA, LCI, LCI_LCA, Letras_Hipotecarias e LIG somados. */
        public string $receivables,
        public string $fiiShares,
    ) {}

    /** `null` sem total investido: não dá para tirar fração de zero. */
    public function kind(): ?FundKind
    {
        $total = BigDecimal::of($this->totalInvested);

        if (! $total->isPositive()) {
            return null;
        }

        return match (true) {
            $this->isMajority($this->realEstate, $total) => FundKind::Brick,
            $this->isMajority($this->receivables, $total) => FundKind::Paper,
            $this->isMajority($this->fiiShares, $total) => FundKind::FundOfFunds,
            default => FundKind::Hybrid,
        };
    }

    /** Estritamente mais da metade: exatos 50% não decidem. */
    private function isMajority(string $part, BigDecimal $total): bool
    {
        return BigDecimal::of($part)->multipliedBy(2)->isGreaterThan($total);
    }
}

<?php

namespace App\Modules\Fiis\Domain;

/**
 * Um fundo da carteira: cotas × preço e cotas × rendimento estimado por cota
 * (docs/superpowers/specs/2026-11-04-carteira-de-fiis-design.md, "Regras").
 * Sem o dado, o valor é null, nunca zero.
 */
final readonly class PortfolioLine
{
    /** @param list<MonthlyDividend> $dividends da competência mais antiga para a mais nova */
    public function __construct(
        public string $ticker,
        public int $shares,
        public ?Money $price,
        public array $dividends,
    ) {}

    public function positionValue(): ?Money
    {
        return $this->price?->times((string) $this->shares);
    }

    public function lastIncome(): ?Money
    {
        $last = $this->dividends === [] ? null : $this->dividends[array_key_last($this->dividends)];

        return $last?->perShare->times((string) $this->shares);
    }

    /** Média dos meses disponíveis, até 12. */
    public function averageIncome(): ?Money
    {
        if ($this->dividends === []) {
            return null;
        }

        $recent = array_slice($this->dividends, -12);

        return Money::average(array_map(fn (MonthlyDividend $d): Money => $d->perShare, $recent))
            ->times((string) $this->shares);
    }

    public function dividendMonths(): int
    {
        return min(count($this->dividends), 12);
    }
}

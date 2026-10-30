<?php

namespace App\Modules\Fiis\Domain;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Guarda uma fração exata, e não um decimal: média ÷ 30 e faixas × 0,95 não têm
 * representação decimal finita, e arredondar antes de comparar poderia mudar se
 * `preço < referência` dispara.
 */
final readonly class Money
{
    private function __construct(private BigRational $amount) {}

    /**
     * Só aceita o formato decimal com ponto. A vírgula da entrada do usuário é
     * normalizada no Form Request, para um valor inválido não virar zero aqui.
     */
    public static function fromString(string $value): self
    {
        if (preg_match('/^\d+(\.\d+)?$/D', $value) !== 1) {
            throw new InvalidArgumentException("Valor monetário inválido: [{$value}]");
        }

        return new self(BigRational::of($value));
    }

    public function isAtMost(self $other): bool
    {
        return $this->amount->isLessThanOrEqualTo($other->amount);
    }

    public function isLessThan(self $other): bool
    {
        return $this->amount->isLessThan($other->amount);
    }

    public function isPositive(): bool
    {
        return $this->amount->isPositive();
    }

    /** Para gravar e para as props: até 6 casas, sem zeros à direita. */
    public function toDecimal(): string
    {
        return (string) $this->amount->toScale(6, RoundingMode::HalfUp)->strippedOfTrailingZeros();
    }

    /**
     * O arredondamento vale só para exibir: as comparações usam o valor exato.
     */
    public function __toString(): string
    {
        [$integer, $cents] = explode('.', (string) $this->amount->toScale(2, RoundingMode::HalfUp));
        $grouped = strrev(implode('.', str_split(strrev($integer), 3)));

        return "R$ {$grouped},{$cents}";
    }
}

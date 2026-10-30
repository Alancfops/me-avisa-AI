<?php

namespace App\Modules\Fiis\Domain;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

/** Variação em pontos percentuais, com sinal: `-3.6` é −3,6%. */
final readonly class Percentage
{
    private function __construct(private BigDecimal $value) {}

    public static function fromString(string $value): self
    {
        if (preg_match('/^-?\d+(\.\d+)?$/D', $value) !== 1) {
            throw new InvalidArgumentException("Percentual inválido: [{$value}]");
        }

        return new self(BigDecimal::of($value));
    }

    public function isBelow(self $other): bool
    {
        return $this->value->isLessThan($other->value);
    }

    public function toDecimal(): string
    {
        return (string) $this->value->strippedOfTrailingZeros();
    }
}

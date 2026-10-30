<?php

namespace App\Modules\Fiis\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/** Variação em pontos percentuais, com sinal: `-3.6` é −3,6%. */
final readonly class Percentage
{
    private const SCALE = 10;

    private const DISPLAY_SCALE = 2;

    private function __construct(private BigDecimal $value) {}

    public static function fromString(string $value): self
    {
        if (preg_match('/^-?\d+(\.\d+)?$/D', $value) !== 1) {
            throw new InvalidArgumentException("Percentual inválido: [{$value}]");
        }

        return new self(BigDecimal::of($value));
    }

    /** Variação de um fechamento ao seguinte, para simular o dia a partir do histórico. */
    public static function change(Money $from, Money $to): self
    {
        $change = $to->ratioTo($from)->minus(1)->multipliedBy(100);

        return new self($change->toScale(self::SCALE, RoundingMode::HalfEven));
    }

    public function isBelow(self $other): bool
    {
        return $this->value->isLessThan($other->value);
    }

    /** Sem sinal e com 2 casas, para frases como "queda de 3,6%". A comparação usa o valor exato. */
    public function magnitude(): string
    {
        $rounded = $this->value->abs()->toScale(self::DISPLAY_SCALE, RoundingMode::HalfUp)->strippedOfTrailingZeros();

        return str_replace('.', ',', (string) $rounded).'%';
    }

    public function toDecimal(): string
    {
        return (string) $this->value->strippedOfTrailingZeros();
    }
}

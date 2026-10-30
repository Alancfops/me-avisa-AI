<?php

namespace App\Modules\Fiis\Domain;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;

/** P/VP guardado como fração exata, para `P/VP < mediana` não depender de arredondamento. */
final readonly class Ratio
{
    private function __construct(private BigRational $value) {}

    public static function of(Money $price, Money $bookValue): self
    {
        return new self($price->ratioTo($bookValue));
    }

    public static function fromString(string $value): self
    {
        return new self(BigRational::of($value));
    }

    /** @param list<self> $values */
    public static function median(array $values): ?self
    {
        if ($values === []) {
            return null;
        }

        usort($values, fn (self $a, self $b): int => $a->value->compareTo($b->value));
        $middle = intdiv(count($values), 2);

        if (count($values) % 2 === 1) {
            return $values[$middle];
        }

        return new self($values[$middle - 1]->value->plus($values[$middle]->value)->dividedBy(2));
    }

    public function isBelow(self $other): bool
    {
        return $this->value->isLessThan($other->value);
    }

    public function toDecimal(): string
    {
        return (string) $this->value->toScale(6, RoundingMode::HalfUp)->strippedOfTrailingZeros();
    }

    public function __toString(): string
    {
        return str_replace('.', ',', (string) $this->value->toScale(2, RoundingMode::HalfUp));
    }
}

<?php

namespace App\Modules\Fiis\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final readonly class Money
{
    private function __construct(private BigDecimal $amount) {}

    /**
     * Só aceita o formato decimal com ponto. A vírgula da entrada do usuário é
     * normalizada no Form Request, para um valor inválido não virar zero aqui.
     */
    public static function fromString(string $value): self
    {
        if (preg_match('/^\d+(\.\d+)?$/D', $value) !== 1) {
            throw new InvalidArgumentException("Valor monetário inválido: [{$value}]");
        }

        return new self(BigDecimal::of($value));
    }

    public function isAtMost(self $other): bool
    {
        return $this->amount->isLessThanOrEqualTo($other->amount);
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

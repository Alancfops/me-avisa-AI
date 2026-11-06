<?php

namespace App\Modules\Fiis\Domain;

/** Soma só as linhas com o dado e conta as que ficaram de fora, para a tela dizer. */
final readonly class PortfolioTotals
{
    public function __construct(
        public ?Money $value,
        public ?Money $lastIncome,
        public ?Money $averageIncome,
        public int $withoutPrice,
        public int $withoutDividends,
    ) {}

    /** @param list<PortfolioLine> $lines */
    public static function of(array $lines): self
    {
        $value = $last = $average = null;
        $withoutPrice = $withoutDividends = 0;

        foreach ($lines as $line) {
            $position = $line->positionValue();

            if ($position === null) {
                $withoutPrice++;
            } else {
                $value = self::add($value, $position);
            }

            $lastIncome = $line->lastIncome();
            $averageIncome = $line->averageIncome();

            if ($lastIncome === null || $averageIncome === null) {
                $withoutDividends++;
            } else {
                $last = self::add($last, $lastIncome);
                $average = self::add($average, $averageIncome);
            }
        }

        return new self($value, $last, $average, $withoutPrice, $withoutDividends);
    }

    private static function add(?Money $sum, Money $amount): Money
    {
        return $sum === null ? $amount : $sum->plus($amount);
    }
}

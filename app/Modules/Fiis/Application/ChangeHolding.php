<?php

namespace App\Modules\Fiis\Application;

/** Muda as cotas de um fundo por total novo ou por quanto somar ou tirar. */
final readonly class ChangeHolding
{
    private const SHARES = 'shares';

    public function __construct(
        private HoldingRepository $holdings,
        private CatalogRepository $catalog,
    ) {}

    /** @throws InvalidInput */
    public function handle(string $ticker, ?int $total, ?int $delta): HoldingChange
    {
        $this->checkArguments($total, $delta);

        if (! $this->catalog->exists($ticker)) {
            throw new InvalidInput(['ticker' => InvalidInput::NOT_IN_CATALOG]);
        }

        $before = $this->holdings->shares($ticker);
        $after = $total ?? $this->afterDelta($ticker, $before, (int) $delta);

        if ($after === 0) {
            if ($before === null) {
                throw $this->notInPortfolio($ticker);
            }

            $this->holdings->remove($ticker);

            return new HoldingChange($ticker, $before, 0, true);
        }

        $this->holdings->save($ticker, $after);

        return new HoldingChange($ticker, $before, $after, false);
    }

    private function checkArguments(?int $total, ?int $delta): void
    {
        if (($total === null) === ($delta === null)) {
            throw new InvalidInput([self::SHARES => 'Informe o total de cotas ou quanto somar ou tirar, não os dois.']);
        }

        if ($delta === 0) {
            throw new InvalidInput([self::SHARES => 'Informe quanto somar ou tirar, diferente de zero.']);
        }

        if ($total !== null && $total < 0) {
            throw new InvalidInput([self::SHARES => 'Informe um número inteiro de cotas, como 100.']);
        }
    }

    private function afterDelta(string $ticker, ?int $before, int $delta): int
    {
        if ($delta > 0) {
            return ($before ?? 0) + $delta;
        }

        if ($before === null) {
            throw $this->notInPortfolio($ticker);
        }

        if ($before + $delta < 0) {
            $take = abs($delta);

            throw new InvalidInput([self::SHARES => "{$ticker} tem {$before} cotas na carteira; não dá para tirar {$take}."]);
        }

        return $before + $delta;
    }

    private function notInPortfolio(string $ticker): InvalidInput
    {
        return new InvalidInput([self::SHARES => "{$ticker} não está na carteira."]);
    }
}

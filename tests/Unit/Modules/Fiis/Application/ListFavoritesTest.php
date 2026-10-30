<?php

use App\Modules\Fiis\Application\CatalogRepository;
use App\Modules\Fiis\Application\ListFavorites;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Domain\Money;
use Tests\Support\Fiis\InMemoryFavorites;
use Tests\Support\Fiis\InMemoryMarketData;

function catalogNaming(array $names): CatalogRepository
{
    return new class($names) implements CatalogRepository
    {
        public function __construct(private array $names) {}

        public function save(array $entries): void {}

        public function search(string $query): array
        {
            return [];
        }

        public function exists(string $ticker): bool
        {
            return isset($this->names[$ticker]);
        }

        public function nameOf(string $ticker): ?string
        {
            return $this->names[$ticker] ?? null;
        }

        public function isEmpty(): bool
        {
            return $this->names === [];
        }
    };
}

it('joins each favorite with its name and latest quote', function () {
    $market = new InMemoryMarketData;
    $market->saveQuote(new Quote('MXRF11', Money::fromString('8.91'), null, null, new DateTimeImmutable('2026-10-30T14:00:00-03:00')));

    $views = (new ListFavorites(new InMemoryFavorites(['MXRF11', 'KNRI11']), catalogNaming(['MXRF11' => 'Maxi Renda']), $market))->handle();

    expect($views)->toHaveCount(2)
        ->and($views[0]->ticker)->toBe('KNRI11')
        ->and($views[0]->name)->toBeNull()
        ->and($views[0]->quote)->toBeNull()
        ->and($views[1]->name)->toBe('Maxi Renda')
        ->and((string) $views[1]->quote->price)->toBe('R$ 8,91');
});

<?php

use App\Modules\Fiis\Application\BuildFundSnapshot;
use App\Modules\Fiis\Application\CatalogRepository;
use App\Modules\Fiis\Application\DescribeFund;
use App\Modules\Fiis\Application\ListFavorites;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Domain\Money;
use Tests\Support\Fiis\FixedClock;
use Tests\Support\Fiis\InMemoryFavorites;
use Tests\Support\Fiis\InMemoryMarketData;
use Tests\Support\Fiis\InMemoryReports;

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

    $favorites = new InMemoryFavorites(['MXRF11', 'KNRI11']);
    $describe = new DescribeFund($favorites, catalogNaming(['MXRF11' => 'Maxi Renda']), $market, new BuildFundSnapshot($market, new InMemoryReports), new InMemoryReports, new FixedClock);
    $views = (new ListFavorites($favorites, $describe))->handle();

    expect($views)->toHaveCount(2)
        ->and($views[0]->ticker)->toBe('KNRI11')
        ->and($views[0]->name)->toBeNull()
        ->and($views[0]->quote)->toBeNull()
        ->and($views[1]->name)->toBe('Maxi Renda')
        ->and((string) $views[1]->quote->price)->toBe('R$ 8,91');
});

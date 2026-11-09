<?php

use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\PriceLock;

it('allows a target exactly 30% away and locks beyond it', function (string $target, bool $locked) {
    $settings = FavoriteSettings::target(Money::fromString($target), false);

    expect(array_keys(PriceLock::exceeded($settings, Money::fromString('10.00'))))
        ->toBe($locked ? ['target'] : []);
})->with([['13.00', false], ['13.01', true], ['7.00', false], ['6.99', true]]);

it('locks only the range end that is too far', function () {
    $settings = FavoriteSettings::range(Money::fromString('9.00'), Money::fromString('14.00'), true);

    expect(array_keys(PriceLock::exceeded($settings, Money::fromString('10.00'))))->toBe(['rangeMax']);
});

it('measures the distance from the current price with sign', function () {
    $settings = FavoriteSettings::range(Money::fromString('9.00'), Money::fromString('14.00'), false);
    $distances = PriceLock::distances($settings, Money::fromString('10.00'));

    expect(array_keys($distances))->toBe(['rangeMin', 'rangeMax'])
        ->and($distances['rangeMin']->toDecimal())->toBe('-10')
        ->and($distances['rangeMax']->toDecimal())->toBe('40');
});

it('has nothing to lock in automatic mode', function () {
    $settings = FavoriteSettings::automatic(true);

    expect(PriceLock::distances($settings, Money::fromString('10.00')))->toBe([])
        ->and(PriceLock::exceeded($settings, Money::fromString('10.00')))->toBe([]);
});

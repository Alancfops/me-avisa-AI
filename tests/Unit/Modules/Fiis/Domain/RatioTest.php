<?php

use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Ratio;

it('divides price by book value exactly', function () {
    $ratio = Ratio::of(Money::fromString('9.50'), Money::fromString('10.00'));

    expect((string) $ratio)->toBe('0,95')
        ->and($ratio->isBelow(Ratio::fromString('0.95')))->toBeFalse()
        ->and($ratio->isBelow(Ratio::fromString('0.96')))->toBeTrue();
});

it('takes the median of an odd list', function () {
    $median = Ratio::median([Ratio::fromString('0.9'), Ratio::fromString('1.1'), Ratio::fromString('1.0')]);

    expect((string) $median)->toBe('1,00');
});

it('takes the mean of the two central values on an even list', function () {
    $median = Ratio::median(array_map(Ratio::fromString(...), ['0.8', '1.0', '0.9', '1.2']));

    expect((string) $median)->toBe('0,95');
});

it('has no median without values', fn () => expect(Ratio::median([]))->toBeNull());

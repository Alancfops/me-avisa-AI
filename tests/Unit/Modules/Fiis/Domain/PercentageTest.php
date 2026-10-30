<?php

use App\Modules\Fiis\Domain\Percentage;

it('compares strictly', function (string $value, bool $expected) {
    expect(Percentage::fromString($value)->isBelow(Percentage::fromString('-3')))->toBe($expected);
})->with([['-3.01', true], ['-3', false], ['-3.00', false], ['-2.99', false], ['0.21', false]]);

it('rejects text that is not a number', function (string $value) {
    expect(fn () => Percentage::fromString($value))->toThrow(InvalidArgumentException::class);
})->with(['', 'abc', '3,5', '1e5']);

it('exposes the decimal value', fn () => expect(Percentage::fromString('-3.60')->toDecimal())->toBe('-3.6'));

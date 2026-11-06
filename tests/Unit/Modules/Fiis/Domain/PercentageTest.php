<?php

use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Percentage;

it('compares strictly', function (string $value, bool $expected) {
    expect(Percentage::fromString($value)->isBelow(Percentage::fromString('-3')))->toBe($expected);
})->with([['-3.01', true], ['-3', false], ['-3.00', false], ['-2.99', false], ['0.21', false]]);

it('rejects text that is not a number', function (string $value) {
    expect(fn () => Percentage::fromString($value))->toThrow(InvalidArgumentException::class);
})->with(['', 'abc', '3,5', '1e5']);

it('exposes the decimal value', fn () => expect(Percentage::fromString('-3.60')->toDecimal())->toBe('-3.6'));

it('measures the change between two closes', function (string $from, string $to, string $expected) {
    expect(Percentage::change(Money::fromString($from), Money::fromString($to))->toDecimal())->toBe($expected);
})->with([
    ['10.00', '9.70', '-3'],
    ['10.00', '10.50', '5'],
    ['10.00', '10.00', '0'],
]);

it('shows the magnitude with two decimals at most, comparing the exact value', function (string $value, string $expected) {
    expect(Percentage::fromString($value)->magnitude())->toBe($expected);
})->with([
    ['-5.4528650647', '5,45%'],
    ['-3.6', '3,6%'],
    ['-3', '3%'],
]);

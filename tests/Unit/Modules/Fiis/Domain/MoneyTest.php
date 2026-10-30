<?php

use App\Modules\Fiis\Domain\Money;

it('rejects invalid prices instead of becoming zero', function (string $value) {
    expect(fn () => Money::fromString($value))->toThrow(InvalidArgumentException::class);
})->with(['', 'abc', '9,50', '-1', "9.50\n"]);

it('formats as Brazilian currency', fn () => expect((string) Money::fromString('9.5'))->toBe('R$ 9,50'));

it('uses a thousands separator', fn () => expect((string) Money::fromString('1234.5'))->toBe('R$ 1.234,50'));

it('compares by exact value', function (string $value, string $other, bool $expected) {
    expect(Money::fromString($value)->isAtMost(Money::fromString($other)))->toBe($expected);
})->with([
    ['9.50', '9.5', true],
    ['9.501', '9.50', false],
    // 0.1 + 0.2 em float vira 0.30000000000000004: aqui o limite é exato.
    ['0.30000000000000004', '0.3', false],
]);

it('knows whether it is positive', function (string $value, bool $expected) {
    expect(Money::fromString($value)->isPositive())->toBe($expected);
})->with([['0', false], ['0.00', false], ['0.01', true]]);

it('compares strictly', function (string $value, string $other, bool $expected) {
    expect(Money::fromString($value)->isLessThan(Money::fromString($other)))->toBe($expected);
})->with([['9.49', '9.50', true], ['9.50', '9.5', false], ['9.51', '9.50', false]]);

it('exposes the decimal value without trailing zeros', function () {
    expect(Money::fromString('149.910000')->toDecimal())->toBe('149.91')
        ->and(Money::fromString('150.00')->toDecimal())->toBe('150');
});

it('averages exactly', function () {
    $average = Money::average([Money::fromString('10'), Money::fromString('10'), Money::fromString('11')]);

    expect((string) $average)->toBe('R$ 10,33')
        ->and(Money::fromString('10.33')->isLessThan($average))->toBeTrue();
});

it('multiplies by a decimal factor', function () {
    expect(Money::fromString('10')->times('0.95')->isAtMost(Money::fromString('9.50')))->toBeTrue()
        ->and(Money::fromString('9.50')->isAtMost(Money::fromString('10')->times('0.95')))->toBeTrue();
});

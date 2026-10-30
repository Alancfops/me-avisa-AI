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

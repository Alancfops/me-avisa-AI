<?php

use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\PriceChange;

it('compares with the close on the target day', function () {
    $closes = [new DailyClose('2026-10-30', Money::fromString('10.00')), new DailyClose('2026-11-05', Money::fromString('10.50'))];
    expect(PriceChange::since($closes, Money::fromString('11.00'), '2026-11-06', 7)->toDecimal())->toBe('10');
});

it('uses the previous session when the target day had none', function () {
    // 2026-11-01 é domingo: vale o fechamento de sexta, 30/10.
    $closes = [new DailyClose('2026-10-30', Money::fromString('8.00'))];
    expect(PriceChange::since($closes, Money::fromString('7.60'), '2026-11-08', 7)->toDecimal())->toBe('-5');
});

it('has no change without a close on or before the target day', function () {
    $closes = [new DailyClose('2026-11-05', Money::fromString('10.00'))];
    expect(PriceChange::since($closes, Money::fromString('10.00'), '2026-11-06', 30))->toBeNull();
});

it('has no change when the nearest close is more than 5 days before the target day', function () {
    $closes = [new DailyClose('2026-09-20', Money::fromString('10.00'))];
    expect(PriceChange::since($closes, Money::fromString('11.00'), '2026-11-06', 30))->toBeNull();
});

it('accepts a close exactly 5 days before the target day', function () {
    // Alvo: 30/10. Fechamento em 25/10, 5 dias antes: ainda vale.
    $closes = [new DailyClose('2026-10-25', Money::fromString('10.00'))];
    expect(PriceChange::since($closes, Money::fromString('11.00'), '2026-11-06', 7)->toDecimal())->toBe('10');
});

it('rejects a close 6 days before the target day', function () {
    $closes = [new DailyClose('2026-10-24', Money::fromString('10.00'))];
    expect(PriceChange::since($closes, Money::fromString('11.00'), '2026-11-06', 7))->toBeNull();
});

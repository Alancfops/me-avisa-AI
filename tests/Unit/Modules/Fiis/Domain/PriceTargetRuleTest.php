<?php

use App\Modules\Fiis\Domain\FundSnapshot;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\PriceTargetRule;

it('fires at or below the target', function (string $price, bool $expected) {
    $rule = new PriceTargetRule(Money::fromString('9.50'));
    $fund = new FundSnapshot('MXRF11', Money::fromString($price));

    expect($rule->evaluate($fund)->matched)->toBe($expected);
})->with([['9.49', true], ['9.50', true], ['9.51', false]]);

it('says why it fired', function () {
    $rule = new PriceTargetRule(Money::fromString('9.50'));
    $fund = new FundSnapshot('MXRF11', Money::fromString('9.42'));

    expect($rule->evaluate($fund)->reason)->toBe('Preço R$ 9,42 no alvo R$ 9,50');
});

it('has no reason when it does not fire', function () {
    $rule = new PriceTargetRule(Money::fromString('9.50'));
    $fund = new FundSnapshot('MXRF11', Money::fromString('9.51'));

    expect($rule->evaluate($fund)->reason)->toBeNull();
});

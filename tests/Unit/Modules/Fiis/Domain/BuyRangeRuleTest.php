<?php

use App\Modules\Fiis\Domain\BuyRangeRule;
use App\Modules\Fiis\Domain\Money;

require_once __DIR__.'/RulesTestHelpers.php';

// Exemplo de docs/fiis/alert.md: entre R$ 9,00 e R$ 9,60, limites inclusos.
it('fires inside the range, both ends included', function (string $price, bool $expected) {
    $rule = new BuyRangeRule(Money::fromString('9.00'), Money::fromString('9.60'));

    expect($rule->evaluate(fund($price))->matched)->toBe($expected);
})->with([
    ['8.99', false],
    ['9.00', true],
    ['9.55', true],
    ['9.60', true],
    ['9.61', false],
]);

it('says why and keeps the reference', function () {
    $result = (new BuyRangeRule(Money::fromString('9.00'), Money::fromString('9.60')))->evaluate(fund('9.55'));

    expect($result->reason)->toBe('Preço R$ 9,55 dentro da faixa R$ 9,00–R$ 9,60')
        ->and($result->reference)->toBe('faixa R$ 9,00–R$ 9,60');
});

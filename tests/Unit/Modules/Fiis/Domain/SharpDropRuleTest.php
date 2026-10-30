<?php

use App\Modules\Fiis\Domain\SharpDropRule;

require_once __DIR__.'/RulesTestHelpers.php';

it('fires only strictly below -3%', function (string $change, bool $expected) {
    expect((new SharpDropRule)->evaluate(fund(change: $change))->matched)->toBe($expected);
})->with([
    ['-3.01', true],
    ['-3.0000001', true],
    ['-3', false],
    ['-3.00', false],
    ['-2.99', false],
    ['0', false],
    ['3.5', false],
]);

it('does not fire without the day change, and says why', function () {
    $result = (new SharpDropRule)->evaluate(fund());

    expect($result->matched)->toBeFalse()
        ->and($result->unavailableReason)->toBe('Variação do dia indisponível.');
});

it('says why it fired', function () {
    $result = (new SharpDropRule)->evaluate(fund('9.42', change: '-3.6'));

    expect($result->reason)->toBe('Queda de 3,6% no dia, preço R$ 9,42')
        ->and($result->reference)->toBe('variação do dia abaixo de −3%');
});

<?php

use App\Modules\Fiis\Domain\AutomaticRule;

require_once __DIR__.'/RulesTestHelpers.php';

const FULL_HISTORY = 60;

it('needs price strictly below average × 0.95 and P/VP strictly below the median', function (string $price, string $pvp, bool $expected) {
    $fund = fund($price, average: '10.00', pvp: $pvp, median: '0.95', days: FULL_HISTORY);

    expect((new AutomaticRule)->evaluate($fund)->matched)->toBe($expected);
})->with([
    'os dois abaixo' => ['9.49', '0.94', true],
    'preço no limite' => ['9.50', '0.94', false],
    'P/VP no limite' => ['9.49', '0.95', false],
    'preço acima' => ['9.51', '0.94', false],
    'P/VP acima' => ['9.49', '0.96', false],
]);

it('compares exactly: a repeating average does not round into a false alarm', function () {
    // Média 10/3 × 0,95 = 3,1666…; 3,17 e 3,16 ficam dos dois lados sem arredondar.
    $rule = new AutomaticRule;

    expect($rule->evaluate(fund('3.17', average: '3.3333333333333333333', pvp: '0.5', median: '0.9', days: 60))->matched)->toBeFalse()
        ->and($rule->evaluate(fund('3.16', average: '3.3333333333333333333', pvp: '0.5', median: '0.9', days: 60))->matched)->toBeTrue();
});

it('falls back to P/VP below 1.0 with fewer than 60 trading days, and says so', function (int $days, string $pvp, string $median, bool $expected, bool $fallback) {
    $result = (new AutomaticRule)->evaluate(fund('9.00', average: '10.00', pvp: $pvp, median: $median, days: $days));

    expect($result->matched)->toBe($expected)
        ->and($result->note !== null)->toBe($fallback);
})->with([
    '59 pregões, abaixo de 1,0' => [59, '0.99', '0.50', true, true],
    '59 pregões, exatamente 1,0' => [59, '1.00', '0.50', false, true],
    '59 pregões, ignora a mediana alta' => [59, '1.01', '2.00', false, true],
    '60 pregões, usa a mediana' => [60, '0.99', '0.50', false, false],
    '60 pregões, abaixo da mediana' => [60, '1.20', '1.30', true, false],
]);

it('names the fallback for the screen', function () {
    $result = (new AutomaticRule)->evaluate(fund('9.00', average: '10.00', pvp: '0.9', days: 10));

    expect($result->note)->toBe('Comparando com P/VP 1,0 até juntar histórico.')
        ->and($result->reference)->toContain('P/VP 1,00');
});

it('does not fire without book value, and says why', function () {
    $result = (new AutomaticRule)->evaluate(fund('5.00', average: '10.00', days: 100));

    expect($result->matched)->toBeFalse()
        ->and($result->unavailableReason)->toBe('VP indisponível: sem o informe da CVM não dá para calcular o P/VP.');
});

it('does not fire without the 30-day average, and says why', function () {
    $result = (new AutomaticRule)->evaluate(fund('5.00', pvp: '0.5', median: '0.9', days: 100));

    expect($result->matched)->toBeFalse()
        ->and($result->unavailableReason)->toBe('Média de 30 pregões indisponível: histórico de preços insuficiente.');
});

it('states the price and the comparison when it fires', function () {
    $result = (new AutomaticRule)->evaluate(fund('9.00', average: '10.00', pvp: '0.90', median: '0.95', days: 60));

    expect($result->reason)->toBe('Preço R$ 9,00 abaixo de R$ 9,50 (média de 30 pregões × 0,95) e P/VP 0,90 abaixo da mediana 0,95')
        ->and($result->reference)->toBe('média 30d R$ 10,00; P/VP 0,90 × mediana 0,95');
});

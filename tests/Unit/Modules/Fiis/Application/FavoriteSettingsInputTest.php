<?php

use App\Modules\Fiis\Application\FavoriteSettingsInput;
use App\Modules\Fiis\Application\InvalidInput;

function inputErrors(string $mode, ?string $target, ?string $min, ?string $max): array
{
    try {
        FavoriteSettingsInput::parse($mode, $target, $min, $max, true);
    } catch (InvalidInput $invalid) {
        return $invalid->errors;
    }

    return [];
}

it('accepts comma and dot as the decimal separator', function (string $typed) {
    $settings = FavoriteSettingsInput::parse('target', $typed, null, null, false);

    expect($settings->target?->toDecimal())->toBe('9.5')
        ->and($settings->sharpDrop)->toBeFalse();
})->with(['9,50', '9.50', ' 9,50 ']);

it('builds a range and the automatic mode', function () {
    $range = FavoriteSettingsInput::parse('range', null, '9,00', '9,60', true);
    $automatic = FavoriteSettingsInput::parse('automatic', null, null, null, true);

    expect($range->rangeMin?->toDecimal())->toBe('9')
        ->and($range->rangeMax?->toDecimal())->toBe('9.6')
        ->and($automatic->target)->toBeNull();
});

it('refuses thousands separators', function () {
    expect(inputErrors('target', '1.234,50', null, null))
        ->toBe(['target' => 'Use só números, como 9,50.']);
});

it('refuses zero', function () {
    expect(inputErrors('target', '0', null, null))
        ->toBe(['target' => 'O valor precisa ser maior que zero.']);
});

it('refuses a range with the maximum below the minimum and accepts equal ends', function () {
    expect(inputErrors('range', null, '9', '8'))
        ->toBe(['rangeMax' => 'O máximo precisa ser maior ou igual ao mínimo.'])
        ->and(inputErrors('range', null, '9', '9'))->toBe([]);
});

it('refuses an unknown mode', function () {
    expect(inputErrors('x', null, null, null))
        ->toBe(['mode' => 'Escolha preço alvo, faixa de compra ou automático.']);
});

it('asks for what the mode needs', function () {
    expect(inputErrors('target', null, null, null))->toBe(['target' => 'Informe o preço alvo.'])
        ->and(inputErrors('range', null, null, '9'))->toBe(['rangeMin' => 'Informe o mínimo da faixa.'])
        ->and(inputErrors('range', null, '9', null))->toBe(['rangeMax' => 'Informe o máximo da faixa.']);
});

it('joins the messages', function () {
    $invalid = new InvalidInput(['a' => 'Um.', 'b' => 'Dois.']);

    expect($invalid->getMessage())->toBe('Um. Dois.');
});

it('uses the field specific format message', function () {
    expect(inputErrors('range', null, 'a', 'b'))->toBe([
        'rangeMin' => 'Use só números, como 9,00.',
        'rangeMax' => 'Use só números, como 9,60.',
    ]);
});

<?php

use App\Modules\Fiis\Application\ChangeHolding;
use App\Modules\Fiis\Application\InvalidInput;
use Tests\Support\Fiis\InMemoryCatalog;
use Tests\Support\Fiis\InMemoryHoldings;

function holdingsWith(array $holdings): InMemoryHoldings
{
    return new InMemoryHoldings($holdings);
}

function changer(InMemoryHoldings $holdings): ChangeHolding
{
    return new ChangeHolding($holdings, new InMemoryCatalog(['MXRF11' => 'Maxi Renda', 'KNRI11' => 'Kinea']));
}

function shareError(InMemoryHoldings $holdings, string $ticker, ?int $total, ?int $delta): array
{
    $before = $holdings->holdings;

    try {
        changer($holdings)->handle($ticker, $total, $delta);
    } catch (InvalidInput $invalid) {
        expect($holdings->holdings)->toBe($before);

        return $invalid->errors;
    }

    throw new RuntimeException('Esperava InvalidInput.');
}

it('sets the total', function () {
    $holdings = holdingsWith(['MXRF11' => 100]);
    $change = changer($holdings)->handle('MXRF11', 150, null);

    expect($holdings->holdings)->toBe(['MXRF11' => 150])
        ->and($change->ticker)->toBe('MXRF11')
        ->and($change->before)->toBe(100)
        ->and($change->after)->toBe(150)
        ->and($change->removed)->toBeFalse();
});

it('adds a fund that was not in the portfolio when setting a total', function () {
    $holdings = holdingsWith([]);
    $change = changer($holdings)->handle('MXRF11', 10, null);

    expect($holdings->holdings)->toBe(['MXRF11' => 10])->and($change->before)->toBeNull();
});

it('adds and subtracts by delta', function () {
    $holdings = holdingsWith(['MXRF11' => 100]);

    expect(changer($holdings)->handle('MXRF11', null, 20)->after)->toBe(120)
        ->and(changer($holdings)->handle('MXRF11', null, -70)->after)->toBe(50)
        ->and($holdings->holdings)->toBe(['MXRF11' => 50]);
});

it('enters the portfolio with a positive delta', function () {
    $holdings = holdingsWith([]);
    $change = changer($holdings)->handle('MXRF11', null, 30);

    expect($holdings->holdings)->toBe(['MXRF11' => 30])
        ->and($change->before)->toBeNull()
        ->and($change->after)->toBe(30);
});

it('removes the holding when the result is zero', function () {
    $holdings = holdingsWith(['MXRF11' => 100, 'KNRI11' => 5]);

    $byTotal = changer($holdings)->handle('MXRF11', 0, null);
    expect($byTotal->removed)->toBeTrue()->and($byTotal->after)->toBe(0)->and($byTotal->before)->toBe(100);

    $byDelta = changer($holdings)->handle('KNRI11', null, -5);
    expect($byDelta->removed)->toBeTrue()->and($holdings->holdings)->toBe([]);
});

it('refuses total and delta together, and none of them', function () {
    expect(shareError(holdingsWith([]), 'MXRF11', 10, 5))
        ->toBe(['shares' => 'Informe o total de cotas ou quanto somar ou tirar, não os dois.'])
        ->and(shareError(holdingsWith([]), 'MXRF11', null, null))
        ->toBe(['shares' => 'Informe o total de cotas ou quanto somar ou tirar.']);
});

it('refuses a zero delta', function () {
    expect(shareError(holdingsWith(['MXRF11' => 1]), 'MXRF11', null, 0))
        ->toBe(['shares' => 'Informe quanto somar ou tirar, diferente de zero.']);
});

it('refuses a negative total', function () {
    expect(shareError(holdingsWith(['MXRF11' => 1]), 'MXRF11', -1, null))
        ->toBe(['shares' => 'Informe um número inteiro de cotas, como 100.']);
});

it('refuses a fund outside the catalog', function () {
    expect(shareError(holdingsWith([]), 'XXXX11', 10, null))
        ->toBe(['ticker' => 'Esse fundo não está no catálogo. Confira o código na B3.']);
});

it('refuses removing a fund that is not in the portfolio', function () {
    expect(shareError(holdingsWith([]), 'MXRF11', 0, null))
        ->toBe(['shares' => 'MXRF11 não está na carteira.'])
        ->and(shareError(holdingsWith([]), 'MXRF11', null, -3))
        ->toBe(['shares' => 'MXRF11 não está na carteira.']);
});

it('refuses taking more shares than the portfolio has', function () {
    expect(shareError(holdingsWith(['MXRF11' => 10]), 'MXRF11', null, -11))
        ->toBe(['shares' => 'MXRF11 tem 10 cotas na carteira; não dá para tirar 11.']);
});

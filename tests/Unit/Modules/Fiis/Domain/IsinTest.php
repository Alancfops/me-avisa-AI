<?php

use App\Modules\Fiis\Domain\Isin;

it('derives the ticker from the ISIN root', function (string $isin, ?string $ticker) {
    expect(Isin::tickerOf($isin))->toBe($ticker);
})->with([
    ['BRMXRFCTF008', 'MXRF11'],
    ['BRKNRICTF007', 'KNRI11'],
    [' brxpmlctf000 ', 'XPML11'],
    ['', null],
    ['BRAB', null],
    ['BRMXRF', null],
    ['000000000000', '000011'],
]);

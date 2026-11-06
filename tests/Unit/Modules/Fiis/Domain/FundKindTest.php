<?php

use App\Modules\Fiis\Domain\FundKind;
use App\Modules\Fiis\Domain\Portfolio;

it('classifies a fund by the share of its portfolio', function (Portfolio $portfolio, ?FundKind $expected) {
    expect($portfolio->kind())->toBe($expected);
})->with([
    'KNRI11 set/26' => [new Portfolio('4670807419.04', '4203284231.19', '389643777.26', '0'), FundKind::Brick],
    'MXRF11 set/26' => [new Portfolio('5176421015.19', '287878537.64', '3892924050.06', '718369408.35'), FundKind::Paper],
    'fundo de fundos 60%' => [new Portfolio('100', '10', '10', '60'), FundKind::FundOfFunds],
    'híbrido 40/40/20' => [new Portfolio('100', '40', '40', '20'), FundKind::Hybrid],
    'exatamente 50% não decide' => [new Portfolio('100', '50', '30', '20'), FundKind::Hybrid],
    'logo acima de 50%' => [new Portfolio('100', '50.01', '30', '19.99'), FundKind::Brick],
    'total zero' => [new Portfolio('0', '0', '0', '0'), null],
    'total negativo' => [new Portfolio('-5', '0', '0', '0'), null],
]);

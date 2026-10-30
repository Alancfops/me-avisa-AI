<?php

use App\Modules\Fiis\Domain\TradingDays;

it('counts the weekdays strictly between two days', function (string $from, string $to, int $expected) {
    expect(TradingDays::missingBetween($from, $to))->toBe($expected);
})->with([
    'colados' => ['2026-10-29', '2026-10-30', 0],
    'sexta a segunda' => ['2026-10-30', '2026-11-02', 0],
    'sexta a terça' => ['2026-10-30', '2026-11-03', 1],
    'virada de ano bissexto' => ['2028-02-28', '2028-03-01', 1],
    'mesmo dia' => ['2026-10-30', '2026-10-30', 0],
    'invertido' => ['2026-10-30', '2026-10-01', 0],
    'semanas' => ['2026-10-01', '2026-10-30', 20],
]);

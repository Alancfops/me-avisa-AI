<?php

use App\Modules\Fiis\Domain\BookValueReport;
use App\Modules\Fiis\Domain\EstimatedDividends;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\MonthlyYield;

it('estimates dividends from yield times book value', function () {
    $dividends = EstimatedDividends::lastTwelve(
        [new BookValueReport('2026-09', '2026-10-10', Money::fromString('163.64'))],
        [new MonthlyYield('2026-09', '2026-10-10', '0.006725')],
        '2026-10-30',
    );

    expect($dividends)->toHaveCount(1)
        ->and($dividends[0]->month)->toBe('2026-09')
        ->and((string) $dividends[0]->perShare)->toBe('R$ 1,10');
});

it('skips months missing either value and reports not yet delivered', function () {
    $dividends = EstimatedDividends::lastTwelve(
        [
            new BookValueReport('2026-06', '2026-07-10', Money::fromString('100.00')),
            new BookValueReport('2026-07', '2026-08-10', Money::fromString('100.00')),
            new BookValueReport('2026-08', '2026-09-10', Money::fromString('100.00')),
            new BookValueReport('2026-09', '2026-10-31', Money::fromString('100.00')),
        ],
        [
            new MonthlyYield('2026-06', '2026-07-10', '0.01'),
            new MonthlyYield('2026-08', '2026-09-10', '0.01'),
            new MonthlyYield('2026-09', '2026-10-10', '0.01'),
        ],
        '2026-10-30',
    );

    expect(array_map(fn ($d) => $d->month, $dividends))->toBe(['2026-06', '2026-08']);
});

it('keeps the 12 most recent months in order', function () {
    $reports = [];
    $yields = [];

    foreach (range(1, 14) as $i) {
        $month = sprintf('%d-%02d', 2025 + intdiv($i - 1, 12), ($i - 1) % 12 + 1);
        $reports[] = new BookValueReport($month, '2026-01-01', Money::fromString('100.00'));
        $yields[] = new MonthlyYield($month, '2026-01-01', '0.01');
    }

    // Embaralhado: a ordem de entrada não pode importar.
    $dividends = EstimatedDividends::lastTwelve(array_reverse($reports), $yields, '2026-10-30');

    expect($dividends)->toHaveCount(12)
        ->and($dividends[0]->month)->toBe('2025-03')
        ->and($dividends[11]->month)->toBe('2026-02');
});

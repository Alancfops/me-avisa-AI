<?php

use App\Modules\Fiis\Domain\BookValueReport;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\PriceHistory;

function historyCloses(int $count, string $value = '10.00', string $from = '2026-01-01'): array
{
    $points = [];
    $day = strtotime($from.' 12:00:00 UTC');

    for ($i = 0; $i < $count; $i++) {
        $points[] = new DailyClose(gmdate('Y-m-d', $day + $i * 86400), Money::fromString($value));
    }

    return $points;
}

it('averages the closes of the window', function () {
    $average = PriceHistory::average(historyCloses(200), '2026-10-30', 6);

    expect((string) $average->average)->toBe('R$ 10,00')
        ->and($average->months)->toBe(6)
        ->and($average->monthsStored)->toBe(6);
});

it('does not average an incomplete window', function () {
    $average = PriceHistory::average(historyCloses(60, '10.00', '2026-08-01'), '2026-10-30', 12);

    expect($average->average)->toBeNull()
        ->and($average->monthsStored)->toBe(2);
});

it('counts only the closes inside the window', function () {
    $history = [
        ...historyCloses(1, '100.00', '2026-01-01'),
        ...historyCloses(60, '10.00', '2026-04-30'),
    ];

    $average = PriceHistory::average($history, '2026-10-30', 6);

    expect((string) $average->average)->toBe('R$ 10,00');
});

it('ignores the closes of the day and after', function () {
    $history = [
        ...historyCloses(200),
        new DailyClose('2026-10-30', Money::fromString('1.00')),
        new DailyClose('2026-11-03', Money::fromString('1.00')),
    ];

    $average = PriceHistory::average($history, '2026-10-30', 6);

    expect((string) $average->average)->toBe('R$ 10,00');
});

it('counts the window as complete when its start day had no trading session', function () {
    // 2026-11-07 foi sábado: o primeiro fechamento guardado é de segunda, 2026-11-09.
    $weekdays = array_values(array_filter(
        historyCloses(380, '10.00', '2026-11-09'),
        fn ($close) => (int) gmdate('N', strtotime($close->date.' 12:00:00 UTC')) < 6,
    ));

    $average = PriceHistory::average($weekdays, '2027-11-08', 12);

    expect($average->monthsStored)->toBe(12)
        ->and((string) $average->average)->toBe('R$ 10,00');
});

it('counts a first close exactly 5 days after the window start as complete', function () {
    $average = PriceHistory::average(historyCloses(200, '10.00', '2026-05-05'), '2026-10-30', 6);

    expect($average->monthsStored)->toBe(6)
        ->and($average->average)->not->toBeNull();
});

it('does not count the window as complete when the first close is 6 days after the start', function () {
    $average = PriceHistory::average(historyCloses(200, '10.00', '2026-05-06'), '2026-10-30', 6);

    expect($average->average)->toBeNull()
        ->and($average->monthsStored)->toBe(5);
});

it('subtracts months from the end of the month without overflowing', function () {
    // 2026-08-31 menos 6 meses é 2026-02-28 (fevereiro não tem dia 31).
    $complete = PriceHistory::average(historyCloses(200, '10.00', '2026-02-28'), '2026-08-31', 6);
    $late = PriceHistory::average(historyCloses(200, '10.00', '2026-03-06'), '2026-08-31', 6);

    expect($complete->monthsStored)->toBe(6)
        ->and($complete->average)->not->toBeNull()
        ->and($late->average)->toBeNull();
});

it('handles the leap day when going back 12 months', function () {
    // 2028-02-29 menos 12 meses é 2027-02-28.
    $complete = PriceHistory::average(historyCloses(400, '10.00', '2027-02-28'), '2028-02-29', 12);
    $late = PriceHistory::average(historyCloses(400, '10.00', '2027-03-06'), '2028-02-29', 12);

    expect($complete->monthsStored)->toBe(12)
        ->and($complete->average)->not->toBeNull()
        ->and($late->average)->toBeNull();
});

it('stores no months without closes', function () {
    $average = PriceHistory::average([], '2026-10-30', 6);

    expect($average->average)->toBeNull()
        ->and($average->monthsStored)->toBe(0);
});

it('pairs each close with the report in force that day', function () {
    $reports = [
        new BookValueReport('2026-01', '2026-02-10', Money::fromString('10.00')),
        new BookValueReport('2026-02', '2026-03-15', Money::fromString('20.00')),
    ];
    $closes = [
        new DailyClose('2026-01-05', Money::fromString('20.00')),
        new DailyClose('2026-03-01', Money::fromString('20.00')),
        new DailyClose('2026-03-20', Money::fromString('20.00')),
    ];

    $points = PriceHistory::points($closes, $reports, '2026-10-30');

    expect($points[0]->priceToBook)->toBeNull()
        ->and((string) $points[1]->priceToBook)->toBe('2,00')
        ->and((string) $points[2]->priceToBook)->toBe('1,00');
});

it('keeps only the last 12 months of points', function () {
    $closes = [
        new DailyClose('2025-10-29', Money::fromString('10.00')),
        new DailyClose('2025-10-30', Money::fromString('11.00')),
        new DailyClose('2026-10-29', Money::fromString('12.00')),
        new DailyClose('2026-10-30', Money::fromString('13.00')),
    ];

    $points = PriceHistory::points($closes, [], '2026-10-30');

    expect(array_map(fn ($p) => $p->date, $points))->toBe(['2025-10-30', '2026-10-29']);
});

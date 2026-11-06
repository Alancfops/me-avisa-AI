<?php

use App\Modules\Fiis\Domain\DividendYield;
use App\Modules\Fiis\Domain\MonthlyYield;

function yields(int $months, string $fraction = '0.01'): array
{
    return array_map(
        fn (int $m) => new MonthlyYield(sprintf('2025-%02d', $m + 1), sprintf('2025-%02d-15', $m + 1), $fraction),
        range(0, $months - 1),
    );
}

it('sums the 12 latest months, as a fraction', function () {
    $yields = [new MonthlyYield('2024-12', '2025-01-15', '0.5'), ...yields(12)];

    expect(DividendYield::trailing12Months($yields, '2026-01-01'))->toBe('0.12');
});

it('has no 12-month figure with fewer than 12 months', function () {
    expect(DividendYield::trailing12Months(yields(11), '2026-01-01'))->toBeNull();
});

it('does not count a report that was not delivered yet', function () {
    expect(DividendYield::trailing12Months(yields(12), '2025-12-14'))->toBeNull()
        ->and(DividendYield::trailing12Months(yields(12), '2025-12-15'))->toBe('0.12');
});

it('sums exactly', function () {
    expect(DividendYield::trailing12Months(yields(12, '0.004342'), '2026-01-01'))->toBe('0.052104');
});

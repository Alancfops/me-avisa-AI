<?php

use App\Modules\Fiis\Domain\BookValueReport;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\FundSnapshot;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Percentage;

function closes(int $count, string $value = '10.00', string $from = '2026-01-01'): array
{
    $points = [];
    $day = strtotime($from.' 12:00:00 UTC');

    for ($i = 0; $i < $count; $i++) {
        $points[] = new DailyClose(gmdate('Y-m-d', $day + $i * 86400), Money::fromString($value));
    }

    return $points;
}

it('averages the last 30 closes, never the live price', function () {
    $history = [
        ...closes(1, '100.00', '2026-01-01'),
        ...closes(30, '10.00', '2026-02-01'),
    ];

    $fund = FundSnapshot::fromHistory('X', Money::fromString('1.00'), null, '2026-03-04', $history, []);

    expect((string) $fund->average30d)->toBe('R$ 10,00');
});

it('leaves the average out with fewer than 30 closes', function () {
    $fund = FundSnapshot::fromHistory('X', Money::fromString('1.00'), null, '2026-03-20', closes(29), []);

    expect($fund->average30d)->toBeNull();
});

it('ignores closes from the day of the quote and after', function () {
    $history = [...closes(30, '10.00', '2026-01-01'), new DailyClose('2026-02-02', Money::fromString('1.00'))];

    $fund = FundSnapshot::fromHistory('X', Money::fromString('1.00'), null, '2026-02-02', $history, []);

    expect((string) $fund->average30d)->toBe('R$ 10,00');
});

it('has no P/VP without a report', function () {
    $fund = FundSnapshot::fromHistory('X', Money::fromString('9.00'), Percentage::fromString('-1'), '2026-03-20', closes(30), []);

    expect($fund->priceToBook)->toBeNull()
        ->and($fund->medianPriceToBook)->toBeNull()
        ->and($fund->priceToBookDays)->toBe(0)
        ->and($fund->changePercent->toDecimal())->toBe('-1');
});

it('uses a report only from its delivery date, with no look-ahead', function () {
    $reports = [
        new BookValueReport('2026-01', '2026-02-10', Money::fromString('10.00')),
        new BookValueReport('2026-02', '2026-03-15', Money::fromString('20.00')),
    ];

    $before = FundSnapshot::fromHistory('X', Money::fromString('10.00'), null, '2026-03-14', [], $reports);
    $onDelivery = FundSnapshot::fromHistory('X', Money::fromString('10.00'), null, '2026-03-15', [], $reports);

    expect((string) $before->priceToBook)->toBe('1,00')
        ->and($before->bookValueMonth)->toBe('2026-01')
        ->and((string) $onDelivery->priceToBook)->toBe('0,50')
        ->and($onDelivery->bookValueMonth)->toBe('2026-02');
});

it('builds each daily P/VP with the report in force that day', function () {
    $reports = [
        new BookValueReport('2026-01', '2026-02-10', Money::fromString('10.00')),
        new BookValueReport('2026-02', '2026-02-20', Money::fromString('20.00')),
    ];
    $history = [
        new DailyClose('2026-02-09', Money::fromString('10.00')), // sem informe público: fica de fora
        new DailyClose('2026-02-10', Money::fromString('10.00')), // 1,00
        new DailyClose('2026-02-19', Money::fromString('9.00')),  // 0,90
        new DailyClose('2026-02-20', Money::fromString('10.00')), // 0,50
    ];

    $fund = FundSnapshot::fromHistory('X', Money::fromString('10.00'), null, '2026-03-01', $history, $reports);

    expect($fund->priceToBookDays)->toBe(3)
        ->and((string) $fund->medianPriceToBook)->toBe('0,90');
});

it('uses a late correction only from when it was delivered', function () {
    // A competência de janeiro foi reentregue depois da de fevereiro: fevereiro segue valendo.
    $reports = [
        new BookValueReport('2026-02', '2026-03-10', Money::fromString('20.00')),
        new BookValueReport('2026-01', '2026-03-20', Money::fromString('10.00')),
    ];

    $fund = FundSnapshot::fromHistory('X', Money::fromString('10.00'), null, '2026-03-25', [], $reports);

    expect($fund->bookValueMonth)->toBe('2026-02');
});

it('keeps only 12 months in the median', function () {
    $reports = [new BookValueReport('2024-01', '2024-02-01', Money::fromString('10.00'))];
    $history = [
        new DailyClose('2025-03-19', Money::fromString('1.00')), // um dia antes da janela
        new DailyClose('2025-03-20', Money::fromString('10.00')),
        new DailyClose('2026-03-19', Money::fromString('10.00')),
    ];

    $fund = FundSnapshot::fromHistory('X', Money::fromString('10.00'), null, '2026-03-20', $history, $reports);

    expect($fund->priceToBookDays)->toBe(2)
        ->and((string) $fund->medianPriceToBook)->toBe('1,00');
});

it('does not average closes that are too old for the quote', function () {
    // Fechamento de 01/08 e preço de 30/10: a média de agosto não diz nada sobre outubro.
    $fund = FundSnapshot::fromHistory('X', Money::fromString('1.00'), null, '2026-10-30', closes(30, '10.00', '2026-07-03'), []);

    expect($fund->average30d)->toBeNull()
        ->and($fund->closesAreStale)->toBeTrue()
        ->and($fund->lastCloseDay)->toBe('2026-08-01');
});

it('tolerates the gap of a long holiday, up to two missing weekdays', function (string $lastClose, string $quoteDay, bool $stale) {
    $history = [];
    $day = strtotime($lastClose.' 12:00:00 UTC');
    for ($i = 29; $i >= 0; $i--) {
        $history[] = new DailyClose(gmdate('Y-m-d', $day - $i * 86400), Money::fromString('10'));
    }

    $fund = FundSnapshot::fromHistory('X', Money::fromString('9'), null, $quoteDay, $history, []);

    expect($fund->closesAreStale)->toBe($stale)
        ->and($fund->average30d === null)->toBe($stale);
})->with([
    'dia seguinte' => ['2026-10-29', '2026-10-30', false],
    'fim de semana' => ['2026-10-30', '2026-11-02', false],
    'carnaval, sexta a quarta' => ['2026-02-13', '2026-02-18', false],
    'três dias úteis sem fechamento' => ['2026-02-13', '2026-02-19', true],
]);

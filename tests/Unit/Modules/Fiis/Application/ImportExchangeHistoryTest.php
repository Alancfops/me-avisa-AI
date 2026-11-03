<?php

use App\Modules\Fiis\Application\ExchangeClose;
use App\Modules\Fiis\Application\ExchangeCloses;
use App\Modules\Fiis\Application\ImportExchangeHistory;
use App\Modules\Fiis\Application\SourceUnavailable;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\Money;
use Psr\Log\NullLogger;
use Tests\Support\Fiis\FixedClock;
use Tests\Support\Fiis\InMemoryCatalog;
use Tests\Support\Fiis\InMemoryMarketData;
use Tests\Support\Fiis\InMemorySourceLog;

/** @param array<int, list<array{string, string}>|Throwable> $years [ticker, dia] por ano */
function yearSource(array $years): ExchangeCloses
{
    return new class($years) implements ExchangeCloses
    {
        /** @var list<int> */
        public array $requested = [];

        public function __construct(private array $years) {}

        public function forDay(string $day): iterable
        {
            return [];
        }

        public function forYear(int $year): iterable
        {
            $this->requested[] = $year;
            $file = $this->years[$year] ?? [];

            if ($file instanceof Throwable) {
                throw $file;
            }

            foreach ($file as [$ticker, $day]) {
                yield new ExchangeClose($ticker, new DailyClose($day, Money::fromString('10.5')));
            }
        }
    };
}

function historyImport(ExchangeCloses $source, InMemoryMarketData $market, ?InMemorySourceLog $log = null, bool $force = false): void
{
    // 2026-11-03: 300 dias antes é 2026-01-07.
    (new ImportExchangeHistory($source, $market, new InMemoryCatalog(['MXRF11' => 'x', 'KNRI11' => 'y']), new FixedClock('2026-11-03 21:00:00-03:00'), new NullLogger, $log ?? new InMemorySourceLog))->handle($force);
}

it('loads the previous and the current year for catalog funds only', function () {
    $market = new InMemoryMarketData;
    $source = yearSource([
        2025 => [['MXRF11', '2025-11-04'], ['ZZZZ11', '2025-11-04']],
        2026 => [['MXRF11', '2026-03-02'], ['KNRI11', '2026-03-02']],
    ]);

    historyImport($source, $market);

    expect($source->requested)->toBe([2025, 2026])
        ->and(array_keys($market->closes))->toBe(['MXRF11', 'KNRI11'])
        ->and(array_keys($market->closes['MXRF11']))->toBe(['2025-11-04', '2026-03-02']);
});

it('skips when there is already a close older than 300 days', function () {
    $market = new InMemoryMarketData;
    $market->saveCloses('MXRF11', [new DailyClose('2026-01-07', Money::fromString('9'))]);
    $source = yearSource([]);

    historyImport($source, $market);

    expect($source->requested)->toBe([]);
});

it('loads when the oldest close is only 299 days old', function () {
    $market = new InMemoryMarketData;
    $market->saveCloses('MXRF11', [new DailyClose('2026-01-08', Money::fromString('9'))]);
    $source = yearSource([]);

    historyImport($source, $market);

    expect($source->requested)->toBe([2025, 2026]);
});

it('reloads with force even when history exists', function () {
    $market = new InMemoryMarketData;
    $market->saveCloses('MXRF11', [new DailyClose('2025-12-01', Money::fromString('9'))]);
    $source = yearSource([2026 => [['MXRF11', '2026-03-02']]]);

    historyImport($source, $market, force: true);

    expect($source->requested)->toBe([2025, 2026])
        ->and($market->closes['MXRF11'])->toHaveKeys(['2025-12-01', '2026-03-02']);
});

it('writes in batches instead of holding the whole year', function () {
    $market = new InMemoryMarketData;
    $rows = [];
    for ($i = 0; $i < ImportExchangeHistory::BATCH_SIZE * 2 + 1; $i++) {
        $rows[] = ['MXRF11', date('Y-m-d', strtotime('2026-01-01 +'.$i.' days'))];
    }

    historyImport(yearSource([2026 => $rows]), $market);

    expect($market->closeWrites)->toBe(3);
});

it('logs the failure, keeps the other year and does not log a success', function () {
    $market = new InMemoryMarketData;
    $log = new InMemorySourceLog;
    $source = yearSource([
        2025 => new SourceUnavailable('A B3 não entregou o arquivo de 2025 (500).'),
        2026 => [['MXRF11', '2026-03-02']],
    ]);

    historyImport($source, $market, $log);

    expect(array_keys($market->closes['MXRF11']))->toBe(['2026-03-02'])
        ->and($log->failures)->toBe([['b3', 'A B3 não entregou o arquivo de 2025 (500).']])
        ->and($log->successes)->toBe([]);
});

it('logs a success when both years load', function () {
    $log = new InMemorySourceLog;

    historyImport(yearSource([]), new InMemoryMarketData, $log);

    expect($log->successes)->toBe(['b3']);
});

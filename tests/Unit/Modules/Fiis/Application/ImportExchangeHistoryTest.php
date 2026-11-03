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
use Tests\Support\Fiis\InMemoryExchangeImports;
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

function historyImport(ExchangeCloses $source, InMemoryMarketData $market, ?InMemorySourceLog $log = null, bool $force = false, ?InMemoryExchangeImports $imports = null, array $catalog = ['MXRF11' => 'x', 'KNRI11' => 'y']): void
{
    (new ImportExchangeHistory($source, $market, $imports ?? new InMemoryExchangeImports, new InMemoryCatalog($catalog), new FixedClock('2026-11-03 21:00:00-03:00'), new NullLogger, $log ?? new InMemorySourceLog))->handle($force);
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

it('downloads nothing when both years are already marked', function () {
    $imports = new InMemoryExchangeImports;
    $imports->markYear(2025);
    $imports->markYear(2026);
    $log = new InMemorySourceLog;
    $source = yearSource([]);

    historyImport($source, new InMemoryMarketData, $log, imports: $imports);

    expect($source->requested)->toBe([])
        ->and($log->successes)->toBe([]);
});

it('loads two years once', function () {
    $imports = new InMemoryExchangeImports;
    historyImport(yearSource([]), new InMemoryMarketData, imports: $imports);

    $second = yearSource([]);
    historyImport($second, new InMemoryMarketData, imports: $imports);

    expect($second->requested)->toBe([]);
});

it('reloads both years with force even when marked', function () {
    $imports = new InMemoryExchangeImports;
    $imports->markYear(2025);
    $imports->markYear(2026);
    $source = yearSource([2026 => [['MXRF11', '2026-03-02']]]);
    $market = new InMemoryMarketData;

    historyImport($source, $market, force: true, imports: $imports);

    expect($source->requested)->toBe([2025, 2026])
        ->and($market->closes['MXRF11'])->toHaveKey('2026-03-02');
});

it('retries only the year that failed on the next run', function (int $failingYear, int $okYear) {
    $imports = new InMemoryExchangeImports;
    $market = new InMemoryMarketData;
    $log = new InMemorySourceLog;
    $first = yearSource([
        $failingYear => new SourceUnavailable("A B3 não entregou o arquivo de {$failingYear} (500)."),
        $okYear => [['MXRF11', "{$okYear}-03-02"]],
    ]);

    historyImport($first, $market, $log, imports: $imports);

    expect($first->requested)->toBe([2025, 2026])
        ->and(array_keys($market->closes['MXRF11']))->toBe(["{$okYear}-03-02"])
        ->and($log->failures)->toBe([['b3', "A B3 não entregou o arquivo de {$failingYear} (500)."]])
        ->and($log->successes)->toBe([]);

    $second = yearSource([$failingYear => [['MXRF11', "{$failingYear}-04-01"]]]);
    historyImport($second, $market, $log, imports: $imports);

    expect($second->requested)->toBe([$failingYear])
        ->and($log->successes)->toBe(['b3']);

    $third = yearSource([]);
    historyImport($third, $market, $log, imports: $imports);

    expect($third->requested)->toBe([]);
})->with([
    'falha o anterior' => [2025, 2026],
    'falha o corrente' => [2026, 2025],
]);

it('does nothing when the catalog is empty', function () {
    $imports = new InMemoryExchangeImports;
    $log = new InMemorySourceLog;
    $source = yearSource([]);

    historyImport($source, new InMemoryMarketData, $log, imports: $imports, catalog: []);

    expect($source->requested)->toBe([])
        ->and($log->successes)->toBe([])
        ->and($imports->hasYear(2026))->toBeFalse();
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

it('logs a success when both years load', function () {
    $log = new InMemorySourceLog;

    historyImport(yearSource([]), new InMemoryMarketData, $log);

    expect($log->successes)->toBe(['b3']);
});

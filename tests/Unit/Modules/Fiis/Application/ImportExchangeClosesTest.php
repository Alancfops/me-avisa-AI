<?php

use App\Modules\Fiis\Application\ExchangeClose;
use App\Modules\Fiis\Application\ExchangeCloses;
use App\Modules\Fiis\Application\ImportExchangeCloses;
use App\Modules\Fiis\Application\SourceUnavailable;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\Money;
use Psr\Log\NullLogger;
use Tests\Support\Fiis\FixedClock;
use Tests\Support\Fiis\InMemoryCatalog;
use Tests\Support\Fiis\InMemoryExchangeImports;
use Tests\Support\Fiis\InMemoryMarketData;
use Tests\Support\Fiis\InMemorySourceLog;

/** @param array<string, list<string>|Throwable> $files tickers por dia; dia ausente = sem arquivo */
function exchangeSource(array $files): ExchangeCloses
{
    return new class($files) implements ExchangeCloses
    {
        /** @var list<string> */
        public array $requested = [];

        public function __construct(private array $files) {}

        public function forDay(string $day): iterable
        {
            $this->requested[] = $day;
            $file = $this->files[$day] ?? [];

            if ($file instanceof Throwable) {
                throw $file;
            }

            foreach ($file as $ticker) {
                yield new ExchangeClose($ticker, new DailyClose($day, Money::fromString('10.5')));
            }
        }

        public function forYear(int $year): iterable
        {
            return [];
        }
    };
}

function exchangeImport(ExchangeCloses $source, InMemoryMarketData $market, InMemoryExchangeImports $imports, ?InMemorySourceLog $log = null): void
{
    // Terça 2026-11-03, 21:00.
    (new ImportExchangeCloses($source, $market, $imports, new InMemoryCatalog(['MXRF11' => 'x', 'KNRI11' => 'y']), new FixedClock('2026-11-03 21:00:00-03:00'), new NullLogger, $log ?? new InMemorySourceLog))->handle();
}

it('imports missing business days for catalog funds only', function () {
    $market = new InMemoryMarketData;
    $source = exchangeSource(['2026-11-03' => ['MXRF11', 'ZZZZ11'], '2026-10-30' => ['KNRI11']]);

    exchangeImport($source, $market, new InMemoryExchangeImports);

    // Os 10 últimos dias úteis, do mais antigo ao mais novo, sem sábado nem domingo.
    expect($source->requested)->toBe(['2026-10-21', '2026-10-22', '2026-10-23', '2026-10-26', '2026-10-27', '2026-10-28', '2026-10-29', '2026-10-30', '2026-11-02', '2026-11-03'])
        ->and(array_keys($market->closes))->toBe(['KNRI11', 'MXRF11'])
        ->and(array_keys($market->closes['MXRF11']))->toBe(['2026-11-03']);
});

it('does not refetch a day already imported', function () {
    $market = new InMemoryMarketData;
    $first = exchangeSource(['2026-11-03' => ['MXRF11']]);
    $imports = new InMemoryExchangeImports;
    exchangeImport($first, $market, $imports);

    $second = exchangeSource(['2026-11-03' => ['MXRF11']]);
    exchangeImport($second, $market, $imports);

    expect($second->requested)->not->toContain('2026-11-03')
        ->and($second->requested)->toHaveCount(9);
});

it('keeps going when one day fails', function () {
    $market = new InMemoryMarketData;
    $log = new InMemorySourceLog;
    $source = exchangeSource(['2026-11-02' => new SourceUnavailable('A B3 não entregou o arquivo de 2026-11-02 (500).'), '2026-11-03' => ['MXRF11']]);

    exchangeImport($source, $market, new InMemoryExchangeImports, $log);

    expect(array_keys($market->closes['MXRF11']))->toBe(['2026-11-03'])
        ->and($log->failures)->toBe([['b3', 'A B3 não entregou o arquivo de 2026-11-02 (500).']]);
});

it('does not mark a missing file as imported, so the next run tries again', function () {
    $market = new InMemoryMarketData;
    $imports = new InMemoryExchangeImports;
    exchangeImport(exchangeSource([]), $market, $imports);

    $next = exchangeSource(['2026-11-03' => ['MXRF11']]);
    exchangeImport($next, $market, $imports);

    expect($next->requested)->toHaveCount(10);
});

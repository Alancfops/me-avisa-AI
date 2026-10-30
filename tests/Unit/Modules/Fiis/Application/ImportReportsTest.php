<?php

use App\Modules\Fiis\Application\FundReportRecord;
use App\Modules\Fiis\Application\FundReports;
use App\Modules\Fiis\Application\ImportReports;
use App\Modules\Fiis\Application\ReportRepository;
use App\Modules\Fiis\Application\SourceUnavailable;
use Psr\Log\NullLogger;
use Tests\Support\Fiis\FixedClock;
use Tests\Support\Fiis\InMemoryCatalog;
use Tests\Support\Fiis\InMemorySourceLog;

function reportSource(): FundReports
{
    return new class implements FundReports
    {
        public function forYear(int $year): array
        {
            return [new FundReportRecord('1', 'BRMXRFCTF008', "{$year}-01", 1, "{$year}-02-01", '9', '0.01')];
        }
    };
}

function failingStore(int $failuresWanted): ReportRepository
{
    return new class($failuresWanted) implements ReportRepository
    {
        public int $saved = 0;

        public function __construct(private int $failures) {}

        public function bookValues(string $ticker): array
        {
            return [];
        }

        public function monthlyYields(string $ticker): array
        {
            return [];
        }

        public function save(array $reports): void
        {
            if ($this->failures-- > 0) {
                throw new RuntimeException('SQLSTATE: numeric overflow');
            }

            $this->saved++;
        }

        public function ambiguousTickers(): array
        {
            return [];
        }
    };
}

it('treats a write failure per year, and imports the other', function () {
    $store = failingStore(1);
    $log = new InMemorySourceLog;

    $result = (new ImportReports(reportSource(), $store, new InMemoryCatalog(['MXRF11' => 'x']), new FixedClock, new NullLogger, $log))->handle();

    expect($store->saved)->toBe(1)
        ->and($result->imported)->toBe(1)
        ->and($log->failures)->toBe([['cvm', 'Não foi possível gravar os informes de 2025.']]);
});

it('fails as a source failure when no year could be written', function () {
    $store = failingStore(2);

    expect(fn () => (new ImportReports(reportSource(), $store, new InMemoryCatalog(['MXRF11' => 'x']), new FixedClock, new NullLogger, new InMemorySourceLog))->handle())
        ->toThrow(SourceUnavailable::class);
});

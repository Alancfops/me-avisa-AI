<?php

use App\Modules\Fiis\Application\CatchUp;
use App\Modules\Fiis\Application\FundReports;
use App\Modules\Fiis\Application\ImportPriceHistory;
use App\Modules\Fiis\Application\ImportReports;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Application\Quotes;
use App\Modules\Fiis\Application\SourceError;
use App\Modules\Fiis\Application\SourceUnavailable;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\Money;
use Psr\Log\NullLogger;
use Tests\Support\Fiis\FixedClock;
use Tests\Support\Fiis\InMemoryCatalog;
use Tests\Support\Fiis\InMemoryFavorites;
use Tests\Support\Fiis\InMemoryMarketData;
use Tests\Support\Fiis\InMemoryReports;
use Tests\Support\Fiis\InMemorySourceLog;

final class CatchUpWorld
{
    public InMemoryFavorites $favorites;

    public InMemoryMarketData $market;

    public InMemorySourceLog $log;

    public FixedClock $clock;

    public int $historyCalls = 0;

    public int $reportCalls = 0;

    /** @var list<string>|SourceUnavailable */
    public array|SourceUnavailable $sourceDays = [];

    public function __construct()
    {
        $this->favorites = new InMemoryFavorites(['KNRI11']);
        $this->market = new InMemoryMarketData;
        $this->log = new InMemorySourceLog;
        $this->clock = new FixedClock('2026-10-30 10:00:00-03:00');
        // A CVM em dia, para os testes de fechamento não dependerem dela.
        $this->log->lastSuccess['cvm'] = new DateTimeImmutable('2026-10-29 20:00:00-03:00');
    }

    public function run(): void
    {
        $world = $this;
        $quotes = new class($world) implements Quotes
        {
            public function __construct(private CatchUpWorld $world) {}

            public function latest(string $ticker): Quote
            {
                throw new LogicException('não usado');
            }

            public function history(string $ticker): array
            {
                $this->world->historyCalls++;

                if ($this->world->sourceDays instanceof SourceUnavailable) {
                    throw $this->world->sourceDays;
                }

                return array_map(fn (string $d) => new DailyClose($d, Money::fromString('10')), $this->world->sourceDays);
            }
        };
        $reports = new class($world) implements FundReports
        {
            public function __construct(private CatchUpWorld $world) {}

            public function forYear(int $year): array
            {
                $this->world->reportCalls++;

                return [];
            }
        };

        (new CatchUp(
            $this->favorites,
            $this->market,
            new ImportPriceHistory($quotes, $this->market, $this->clock),
            new ImportReports($reports, new InMemoryReports, new InMemoryCatalog, $this->clock, new NullLogger, $this->log),
            $this->log,
            $this->clock,
            new NullLogger,
        ))->handle();
    }

    public function close(string $day): void
    {
        $this->market->saveCloses('KNRI11', [new DailyClose($day, Money::fromString('10'))]);
    }
}

it('fetches nothing when the closes are up to date', function () {
    $world = new CatchUpWorld;
    $world->close('2026-10-29');

    $world->run();

    expect($world->historyCalls)->toBe(0)->and($world->reportCalls)->toBe(0);
});

it('recovers a lost close and stays quiet about it', function () {
    $world = new CatchUpWorld;
    $world->close('2026-10-28');
    $world->sourceDays = ['2026-10-28', '2026-10-29'];

    $world->run();

    expect($world->historyCalls)->toBe(1)
        ->and(array_keys($world->market->closes['KNRI11']))->toContain('2026-10-29')
        ->and($world->log->failures)->toBe([]);
});

it('recovers a fund with no closes at all', function () {
    $world = new CatchUpWorld;
    $world->sourceDays = ['2026-10-29'];

    $world->run();

    expect($world->historyCalls)->toBe(1);
});

it('tries only once a day when the source has nothing newer, as on a holiday', function () {
    $world = new CatchUpWorld;
    $world->close('2026-10-28');
    $world->sourceDays = ['2026-10-28'];

    $world->run();
    $world->run();
    expect($world->historyCalls)->toBe(1);

    $world->clock->current = new DateTimeImmutable('2026-10-31 10:00:00-03:00');
    $world->run();
    expect($world->historyCalls)->toBe(2);
});

it('tries again on the next round when the source failed', function () {
    $world = new CatchUpWorld;
    $world->close('2026-10-28');
    $world->sourceDays = new SourceUnavailable('fora do ar');

    $world->run();
    $world->run();

    expect($world->historyCalls)->toBe(2)
        ->and($world->log->failures[0])->toBe(['brapi', 'KNRI11: fora do ar']);
});

it('registers on the health page when the closes stay too old', function () {
    $world = new CatchUpWorld;
    $world->close('2026-08-03');
    $world->sourceDays = ['2026-08-03'];

    $world->run();

    expect($world->log->failures)->toBe([['brapi', 'KNRI11: fechamentos desatualizados, o último é de 03/08/2026. A regra automática fica sem disparar até recuperar.']]);
});

it('does not complain about a short gap, as after a holiday', function () {
    $world = new CatchUpWorld;
    $world->close('2026-10-28');
    $world->sourceDays = ['2026-10-28'];

    $world->run();

    expect($world->log->failures)->toBe([]);
});

it('recovers lost CVM imports', function (?string $lastSuccess, int $calls) {
    $world = new CatchUpWorld;
    $world->close('2026-10-29');
    unset($world->log->lastSuccess['cvm']);
    if ($lastSuccess !== null) {
        $world->log->lastSuccess['cvm'] = new DateTimeImmutable($lastSuccess);
    }

    $world->run();

    expect($world->reportCalls)->toBe($calls);
})->with([
    'nunca importou' => [null, 2],
    'perdeu a de ontem' => ['2026-10-28 20:00:00-03:00', 2],
    'dentro das 26 h' => ['2026-10-29 08:01:00-03:00', 0],
]);

it('waits before retrying a CVM import that just failed', function () {
    $world = new CatchUpWorld;
    $world->close('2026-10-29');
    unset($world->log->lastSuccess['cvm']);
    $world->log->recentErrors['cvm'] = [new SourceError('fora do ar', new DateTimeImmutable('2026-10-30 09:30:00-03:00'))];

    $world->run();

    expect($world->reportCalls)->toBe(0);
});

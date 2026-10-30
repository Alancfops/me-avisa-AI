<?php

use App\Modules\Fiis\Application\DailyClose;
use App\Modules\Fiis\Application\ImportPriceHistory;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Application\Quotes;
use App\Modules\Fiis\Domain\Money;
use Tests\Support\Fiis\FixedClock;
use Tests\Support\Fiis\InMemoryMarketData;

function historyReturning(array $days): Quotes
{
    return new class($days) implements Quotes
    {
        public function __construct(private array $days) {}

        public function latest(string $ticker): Quote
        {
            throw new LogicException('não usado');
        }

        public function history(string $ticker): array
        {
            return array_map(fn (string $d) => new DailyClose($d, Money::fromString('10')), $this->days);
        }
    };
}

it('does not take todays bar as a close during the session', function (string $now, array $expected) {
    $market = new InMemoryMarketData;
    $quotes = historyReturning(['2026-10-29', '2026-10-30']);

    (new ImportPriceHistory($quotes, $market, new FixedClock($now)))->handle('KNRI11');

    expect(array_keys($market->closes['KNRI11']))->toBe($expected);
})->with([
    'pregão aberto' => ['2026-10-30 14:00:00-03:00', ['2026-10-29']],
    'um minuto antes do corte' => ['2026-10-30 18:59:00-03:00', ['2026-10-29']],
    'no corte' => ['2026-10-30 19:00:00-03:00', ['2026-10-29', '2026-10-30']],
    // 22:30 em São Paulo já é dia 31 em UTC: o dia do mercado é o de São Paulo.
    'noite em UTC' => ['2026-10-31 01:30:00+00:00', ['2026-10-29', '2026-10-30']],
]);

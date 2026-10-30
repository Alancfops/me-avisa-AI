<?php

use App\Modules\Fiis\Application\DailyClose;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Application\Quotes;
use App\Modules\Fiis\Application\RefreshQuotes;
use App\Modules\Fiis\Application\SourceUnavailable;
use App\Modules\Fiis\Domain\Money;
use Psr\Log\AbstractLogger;
use Tests\Support\Fiis\InMemoryFavorites;
use Tests\Support\Fiis\InMemoryMarketData;

function quoteOf(string $ticker, string $price, string $at = '2026-10-30T14:00:00-03:00'): Quote
{
    return new Quote($ticker, Money::fromString($price), null, null, new DateTimeImmutable($at));
}

function quotesReturning(array $answers): Quotes
{
    return new class($answers) implements Quotes
    {
        public array $asked = [];

        public function __construct(private array $answers) {}

        public function latest(string $ticker): Quote
        {
            $this->asked[] = $ticker;
            $answer = $this->answers[$ticker];

            return $answer instanceof Throwable ? throw $answer : $answer;
        }

        /** @return list<DailyClose> */
        public function history(string $ticker): array
        {
            return [];
        }
    };
}

function spyLogger(): AbstractLogger
{
    return new class extends AbstractLogger
    {
        public array $records = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->records[] = [$level, (string) $message, $context];
        }
    };
}

it('keeps refreshing the other funds when one fails, and keeps the good data', function () {
    $market = new InMemoryMarketData;
    $market->saveQuote(quoteOf('VISC11', '10.00', '2026-10-29T17:00:00-03:00'));

    $quotes = quotesReturning([
        'KNRI11' => quoteOf('KNRI11', '149.91'),
        'MXRF11' => quoteOf('MXRF11', '8.91'),
        'VISC11' => new SourceUnavailable('resposta inválida'),
    ]);
    $logger = spyLogger();

    $result = (new RefreshQuotes(new InMemoryFavorites(['KNRI11', 'VISC11', 'MXRF11']), $quotes, $market, $logger))->handle();

    expect($quotes->asked)->toBe(['KNRI11', 'MXRF11', 'VISC11'])
        ->and($result->updated)->toBe(['KNRI11', 'MXRF11'])
        ->and($result->failed)->toBe(['VISC11'])
        ->and((string) $market->latestQuote('VISC11')->price)->toBe('R$ 10,00')
        ->and((string) $market->latestQuote('KNRI11')->price)->toBe('R$ 149,91')
        ->and($logger->records)->toHaveCount(1)
        ->and($logger->records[0][1])->toContain('VISC11');
});

it('does not ask for the same ticker twice in a round', function () {
    $quotes = quotesReturning(['KNRI11' => quoteOf('KNRI11', '149.91')]);

    (new RefreshQuotes(new InMemoryFavorites(['KNRI11']), $quotes, new InMemoryMarketData, spyLogger()))->handle();

    expect($quotes->asked)->toBe(['KNRI11']);
});

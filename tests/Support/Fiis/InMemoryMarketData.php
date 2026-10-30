<?php

namespace Tests\Support\Fiis;

use App\Modules\Fiis\Application\MarketData;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Domain\DailyClose;

final class InMemoryMarketData implements MarketData
{
    /** @var array<string, Quote> */
    public array $quotes = [];

    /** @var array<string, array<string, DailyClose>> */
    public array $closes = [];

    public function latestQuote(string $ticker): ?Quote
    {
        return $this->quotes[$ticker] ?? null;
    }

    public function saveQuote(Quote $quote): void
    {
        $current = $this->quotes[$quote->ticker] ?? null;

        if ($current === null || $quote->quotedAt >= $current->quotedAt) {
            $this->quotes[$quote->ticker] = $quote;
        }
    }

    public function saveCloses(string $ticker, array $closes): void
    {
        foreach ($closes as $close) {
            $this->closes[$ticker][$close->date] = $close;
        }
    }

    public function closesBefore(string $ticker, string $day, string $fromDay): array
    {
        $closes = array_filter(
            $this->closes[$ticker] ?? [],
            fn (DailyClose $close): bool => $close->date < $day && $close->date >= $fromDay,
        );
        ksort($closes);

        return array_values($closes);
    }
}

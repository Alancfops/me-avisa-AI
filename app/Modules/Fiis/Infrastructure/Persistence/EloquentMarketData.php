<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use App\Modules\Fiis\Application\MarketData;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Percentage;

final class EloquentMarketData implements MarketData
{
    public function latestQuote(string $ticker): ?Quote
    {
        $row = QuoteModel::query()->find($ticker);

        if ($row === null) {
            return null;
        }

        return new Quote(
            $row->ticker,
            Money::fromString($row->price),
            $row->change_percent === null ? null : Percentage::fromString($row->change_percent),
            $row->volume,
            $row->quoted_at->toDateTimeImmutable(),
        );
    }

    public function saveQuote(Quote $quote): void
    {
        $current = $this->latestQuote($quote->ticker);

        if ($current !== null && $quote->quotedAt < $current->quotedAt) {
            return;
        }

        QuoteModel::query()->updateOrCreate(['ticker' => $quote->ticker], [
            'price' => $quote->price->toDecimal(),
            'change_percent' => $quote->changePercent?->toDecimal(),
            'volume' => $quote->volume,
            'quoted_at' => $quote->quotedAt,
        ]);
    }

    public function deleteQuote(string $ticker): void
    {
        QuoteModel::query()->where('ticker', $ticker)->delete();
    }

    public function saveCloses(string $ticker, array $closes): void
    {
        if ($closes === []) {
            return;
        }

        DailyPriceModel::query()->upsert(
            array_map(fn (DailyClose $close): array => [
                'ticker' => $ticker,
                'day' => $close->date,
                'close' => $close->close->toDecimal(),
            ], $closes),
            ['ticker', 'day'],
            ['close'],
        );
    }

    public function closesBefore(string $ticker, string $day, string $fromDay): array
    {
        return array_values(DailyPriceModel::query()
            ->where('ticker', $ticker)
            ->where('day', '<', $day)
            ->where('day', '>=', $fromDay)
            ->orderBy('day')
            ->get()
            ->map(fn (DailyPriceModel $row): DailyClose => new DailyClose($row->day, Money::fromString($row->close)))
            ->all());
    }
}

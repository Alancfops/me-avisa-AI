<?php

namespace App\Modules\Fiis\Infrastructure\Persistence;

use App\Modules\Fiis\Application\MarketData;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Percentage;
use App\Modules\Fiis\Domain\TradingDays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

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

    public function latestClose(string $ticker): ?DailyClose
    {
        $row = DailyPriceModel::query()->where('ticker', $ticker)->orderByDesc('day')->first();

        return $row === null ? null : new DailyClose($row->day, Money::fromString($row->close));
    }

    public function latestCloses(int $tradingDays = 10): array
    {
        $latest = DailyPriceModel::query()->max('day');

        if (! is_string($latest)) {
            return [];
        }

        $from = CarbonImmutable::parse($latest);

        for ($counted = $this->isBusinessDay($from) ? 1 : 0; $counted < $tradingDays;) {
            $from = $from->subDay();
            $counted += $this->isBusinessDay($from) ? 1 : 0;
        }

        $closes = [];

        // Uma consulta: DISTINCT ON fica com a linha mais nova de cada ticker.
        $rows = DB::table('daily_prices')
            ->selectRaw('distinct on (ticker) ticker, day, close')
            ->where('day', '>=', $from->toDateString())
            ->orderBy('ticker')
            ->orderByDesc('day')
            ->get();

        foreach ($rows as $row) {
            $closes[(string) $row->ticker] = new DailyClose(substr((string) $row->day, 0, 10), Money::fromString((string) $row->close));
        }

        return $closes;
    }

    private function isBusinessDay(CarbonImmutable $day): bool
    {
        return TradingDays::isBusinessDay($day->toDateString());
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

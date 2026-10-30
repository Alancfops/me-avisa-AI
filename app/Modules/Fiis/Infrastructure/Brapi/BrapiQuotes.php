<?php

namespace App\Modules\Fiis\Infrastructure\Brapi;

use App\Modules\Fiis\Application\DailyClose;
use App\Modules\Fiis\Application\MarketTime;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Application\Quotes;
use App\Modules\Fiis\Application\SourceUnavailable;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\Percentage;
use DateTimeImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class BrapiQuotes implements Quotes
{
    /** Tolerância de relógio: cotação mais à frente que isso é dado quebrado. */
    private const FUTURE_TOLERANCE_MINUTES = 60;

    public function __construct(private BrapiClient $client) {}

    public function latest(string $ticker): Quote
    {
        $data = $this->dataFor($ticker, $this->client->get('/api/v2/stocks/quote', ['symbols' => $ticker]));

        $price = BrapiNumbers::decimal($data['regularMarketPrice'] ?? null);

        if ($price === null || ! Money::fromString($this->nonNegative($price))->isPositive()) {
            throw new SourceUnavailable('A brapi enviou um preço inválido.');
        }

        return new Quote(
            $ticker,
            Money::fromString($price),
            $this->changePercent($data),
            is_int($data['regularMarketVolume'] ?? null) ? $data['regularMarketVolume'] : null,
            $this->quotedAt($data['regularMarketTime'] ?? null),
        );
    }

    public function history(string $ticker): array
    {
        $response = $this->client->get('/api/v2/stocks/historical', ['symbols' => $ticker, 'range' => '3mo', 'interval' => '1d']);
        $bars = $this->dataFor($ticker, $response)['historicalDataPrice'] ?? null;

        if (! is_array($bars)) {
            throw new SourceUnavailable('A brapi enviou um histórico inválido.');
        }

        $closes = [];

        foreach ($bars as $bar) {
            $close = $this->closeFrom($bar);

            if ($close === null) {
                Log::warning("Fechamento inválido da brapi ignorado em {$ticker}.");

                continue;
            }

            $closes[$close->date] = $close;
        }

        ksort($closes);

        return array_values($closes);
    }

    /**
     * @param  array<mixed>  $body
     * @return array<mixed>
     */
    private function dataFor(string $ticker, array $body): array
    {
        $result = Arr::get($body, 'results.0');
        $data = is_array($result) ? ($result['data'] ?? null) : null;

        if (! is_array($result) || ! is_array($data) || BrapiNumbers::normalizeTicker($result['symbol'] ?? null) !== $ticker) {
            throw new SourceUnavailable('A brapi enviou uma resposta inválida.');
        }

        return $data;
    }

    private function nonNegative(string $decimal): string
    {
        return str_starts_with($decimal, '-') ? '0' : $decimal;
    }

    /** @param array<mixed> $data */
    private function changePercent(array $data): ?Percentage
    {
        $raw = $data['regularMarketChangePercent'] ?? null;

        if ($raw === null) {
            return null;
        }

        $decimal = BrapiNumbers::decimal($raw);

        if ($decimal === null) {
            throw new SourceUnavailable('A brapi enviou uma variação inválida.');
        }

        return Percentage::fromString($decimal);
    }

    private function quotedAt(mixed $raw): DateTimeImmutable
    {
        if (! is_string($raw) || preg_match('/^\d{4}-\d{2}-\d{2}T/', $raw) !== 1) {
            throw new SourceUnavailable('A brapi enviou uma cotação sem horário válido.');
        }

        try {
            $moment = Carbon::parse($raw);
        } catch (Throwable) {
            throw new SourceUnavailable('A brapi enviou uma cotação sem horário válido.');
        }

        if ($moment->isAfter(Carbon::now()->addMinutes(self::FUTURE_TOLERANCE_MINUTES))) {
            throw new SourceUnavailable('A brapi enviou uma cotação com horário no futuro.');
        }

        return $moment->toDateTimeImmutable();
    }

    /** Usa `close` de propósito: `adjustedClose` reescreve o passado a cada provento. */
    private function closeFrom(mixed $bar): ?DailyClose
    {
        if (! is_array($bar) || ! is_int($bar['date'] ?? null)) {
            return null;
        }

        $close = BrapiNumbers::decimal($bar['close'] ?? null);

        if ($close === null || ! Money::fromString($this->nonNegative($close))->isPositive()) {
            return null;
        }

        return new DailyClose(
            MarketTime::day((new DateTimeImmutable)->setTimestamp($bar['date'])),
            Money::fromString($close),
        );
    }
}

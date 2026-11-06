<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\FundSnapshot;
use App\Modules\Fiis\Domain\TradingDays;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

/**
 * A agenda não recupera execução perdida: com a máquina desligada às 19:00 ou às
 * 20:00, fechamento e informe simplesmente não vinham. Aqui se busca o que faltou.
 */
final readonly class CatchUp
{
    /** A importação roda a cada 24 h; a folga evita disputar com o horário agendado. */
    private const REPORTS_MAX_AGE_HOURS = 26;

    private const REPORTS_RETRY_HOURS = 2;

    public function __construct(
        private FavoriteRepository $favorites,
        private MarketData $market,
        private ImportPriceHistory $history,
        private ImportReports $reports,
        private SourceLog $sourceLog,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    public function handle(): void
    {
        $today = MarketTime::day($this->clock->now());

        foreach ($this->favorites->tickers() as $ticker) {
            $this->catchUpCloses($ticker, $today);
        }

        $this->catchUpReports();
    }

    private function catchUpCloses(string $ticker, string $today): void
    {
        $last = $this->lastCloseDay($ticker, $today);

        if (($last !== null && TradingDays::missingBetween($last, $today) === 0)
            || $this->favorites->historyCheckedOn($ticker) === $today) {
            return;
        }

        try {
            $this->history->handle($ticker);
            // Feriado também cai aqui: a fonte não tem nada mais novo, e uma tentativa por dia basta.
            $this->favorites->markHistoryChecked($ticker, $today);
            $this->reportIfStillStale($ticker, $today);
        } catch (SourceUnavailable $failure) {
            $this->logger->warning("Fechamentos de {$ticker} não recuperados: {$failure->getMessage()}");
            $this->sourceLog->failure(Source::Brapi, "{$ticker}: {$failure->getMessage()}");
        }
    }

    private function reportIfStillStale(string $ticker, string $today): void
    {
        $last = $this->lastCloseDay($ticker, $today);

        if ($last !== null && TradingDays::missingBetween($last, $today) > FundSnapshot::MAX_MISSING_TRADING_DAYS) {
            $day = implode('/', array_reverse(explode('-', $last)));
            $this->sourceLog->failure(Source::Brapi, "{$ticker}: fechamentos desatualizados, o último é de {$day}. A regra automática fica sem disparar até recuperar.");
        }
    }

    private function lastCloseDay(string $ticker, string $today): ?string
    {
        $closes = $this->market->closesBefore($ticker, $today, MarketTime::yearBefore($today));
        $last = end($closes);

        return $last instanceof DailyClose ? $last->date : null;
    }

    private function catchUpReports(): void
    {
        $now = $this->clock->now();
        $status = $this->sourceLog->status(Source::Cvm);

        if ($this->isWithin($status->lastSuccessAt, $now, self::REPORTS_MAX_AGE_HOURS)
            || $this->isWithin(($status->errors[0] ?? null)?->occurredAt, $now, self::REPORTS_RETRY_HOURS)) {
            return;
        }

        try {
            $this->reports->handle();
        } catch (SourceUnavailable) {
            // ImportReports já registrou a falha de cada ano; a próxima rodada tenta de novo.
        }
    }

    private function isWithin(?DateTimeImmutable $moment, DateTimeImmutable $now, int $hours): bool
    {
        return $moment !== null && $moment > $now->modify("-{$hours} hours");
    }
}

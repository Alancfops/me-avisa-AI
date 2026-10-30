<?php

namespace App\Modules\Fiis\Domain;

final readonly class FundSnapshot
{
    /** docs/fiis/alert.md, "Média de 30 dias". */
    public const AVERAGE_WINDOW = 30;

    public function __construct(
        public string $ticker,
        public Money $price,
        public ?Percentage $changePercent = null,
        public ?Money $average30d = null,
        public ?Ratio $priceToBook = null,
        public ?Ratio $medianPriceToBook = null,
        public int $priceToBookDays = 0,
        /** Competência do VP usado, em `Y-m`. */
        public ?string $bookValueMonth = null,
    ) {}

    /**
     * Monta o retrato como ele era em `$day`: nenhum fechamento desse dia em diante
     * e nenhum informe ainda não entregue. É a mesma conta no app e no simulador.
     *
     * @param  list<DailyClose>  $closes
     * @param  list<BookValueReport>  $reports
     */
    public static function fromHistory(
        string $ticker,
        Money $price,
        ?Percentage $changePercent,
        string $day,
        array $closes,
        array $reports,
    ): self {
        $earlier = array_values(array_filter($closes, fn (DailyClose $close): bool => $close->date < $day));
        usort($earlier, fn (DailyClose $a, DailyClose $b): int => $a->date <=> $b->date);

        $report = self::reportInForce($reports, $day);
        $dailyRatios = self::dailyRatios($earlier, $reports, self::twelveMonthsBefore($day));

        return new self(
            $ticker,
            $price,
            $changePercent,
            self::average30d($earlier),
            $report === null ? null : Ratio::of($price, $report->bookValue),
            Ratio::median($dailyRatios),
            count($dailyRatios),
            $report?->month,
        );
    }

    /** @param list<DailyClose> $earlier em ordem de data */
    private static function average30d(array $earlier): ?Money
    {
        if (count($earlier) < self::AVERAGE_WINDOW) {
            return null;
        }

        return Money::average(array_map(
            fn (DailyClose $close): Money => $close->close,
            array_slice($earlier, -self::AVERAGE_WINDOW),
        ));
    }

    /**
     * @param  list<DailyClose>  $earlier
     * @param  list<BookValueReport>  $reports
     * @return list<Ratio>
     */
    private static function dailyRatios(array $earlier, array $reports, string $fromDay): array
    {
        $ratios = [];

        foreach ($earlier as $close) {
            $report = $close->date >= $fromDay ? self::reportInForce($reports, $close->date) : null;

            if ($report !== null) {
                $ratios[] = Ratio::of($close->close, $report->bookValue);
            }
        }

        return $ratios;
    }

    /**
     * O informe de competência mais recente entre os já entregues naquele dia.
     *
     * @param  list<BookValueReport>  $reports
     */
    private static function reportInForce(array $reports, string $day): ?BookValueReport
    {
        $inForce = null;

        foreach ($reports as $report) {
            if ($report->deliveredOn <= $day && ($inForce === null || $report->month > $inForce->month)) {
                $inForce = $report;
            }
        }

        return $inForce;
    }

    private static function twelveMonthsBefore(string $day): string
    {
        return ((int) substr($day, 0, 4) - 1).substr($day, 4);
    }
}

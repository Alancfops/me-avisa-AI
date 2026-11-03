import {
    CartesianGrid,
    Line,
    LineChart,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    ChartPeriod,
    noHistoryArea,
    axisTick,
    dateFromTime,
    periodWords,
    tickLabel,
    tooltipStyle,
} from '@/components/fund/chart-period';
import { LegendItem } from '@/components/fund/price-chart';
import { dayMonth, ratio } from '@/lib/format';
import {
    axisTicks,
    inPeriod,
    isIncomplete,
    monthlyLast,
    paddedDomain,
    periodStart,
    toTime,
} from '@/lib/history';
import type { HistoryPoint, Period } from '@/lib/history';

function plain(value: number): string {
    return value.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export function PriceToBookChart({
    history,
    period,
    onPeriodChange,
}: {
    history: HistoryPoint[];
    period: Period;
    onPeriodChange: (period: Period) => void;
}) {
    const lastDay =
        history.length > 0 ? history[history.length - 1].date : null;
    // A janela segue o último dia de todo o histórico; só depois saem os dias
    // sem P/VP (fundo sem informe naquele dia).
    const points = inPeriod(history, period).filter(
        (point): point is HistoryPoint & { priceToBook: string } =>
            point.priceToBook !== null,
    );

    return (
        <section aria-labelledby="pvp" className="surface p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 id="pvp" className="text-lg font-semibold">
                    P/VP ao longo do tempo
                </h2>
                <ChartPeriod
                    value={period}
                    onChange={onPeriodChange}
                    label="Período do gráfico de P/VP"
                />
            </div>

            {lastDay === null || points.length === 0 ? (
                <p className="mt-4 text-sm text-muted-foreground">
                    Ainda sem P/VP calculado neste período. Ele depende do
                    informe da CVM e de um fechamento guardado por dia.
                </p>
            ) : (
                <Body points={points} lastDay={lastDay} period={period} />
            )}
        </section>
    );
}

function Body({
    points,
    lastDay,
    period,
}: {
    points: (HistoryPoint & { priceToBook: string })[];
    lastDay: string;
    period: Period;
}) {
    const start = toTime(periodStart(lastDay, period));
    const end = toTime(lastDay);
    const data = points.map((point) => ({
        time: toTime(point.date),
        value: Number(point.priceToBook),
    }));
    const domain = paddedDomain([...data.map((d) => d.value), 1]);
    const first = points[0].priceToBook;
    const last = points[points.length - 1].priceToBook;
    const rows = monthlyLast(points);
    const incomplete = isIncomplete(points, period);

    return (
        <>
            <div
                role="img"
                aria-label={`P/VP foi de ${ratio(first)} para ${ratio(last)} no histórico guardado dos últimos ${periodWords(period)}; a referência é 1,00`}
                className="mt-4 h-52 w-full"
            >
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart
                        data={data}
                        margin={{ top: 8, right: 8, bottom: 0, left: 0 }}
                        accessibilityLayer={false}
                    >
                        <CartesianGrid
                            stroke="var(--border)"
                            vertical={false}
                        />
                        <XAxis
                            dataKey="time"
                            type="number"
                            scale="time"
                            domain={[start, end]}
                            ticks={axisTicks(start, end)}
                            tickFormatter={(t: number) => tickLabel(t, period)}
                            tick={axisTick}
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                        />
                        <YAxis
                            domain={domain}
                            tickCount={5}
                            tickFormatter={(v: number) => plain(v)}
                            tick={axisTick}
                            tickLine={false}
                            axisLine={false}
                            width={40}
                        />
                        <Tooltip
                            {...tooltipStyle}
                            labelFormatter={(t) =>
                                dayMonth(dateFromTime(Number(t)))
                            }
                            formatter={(v) => [plain(Number(v)), 'P/VP']}
                        />
                        {incomplete && noHistoryArea(start, data[0].time)}
                        <ReferenceLine
                            y={1}
                            stroke="var(--muted-foreground)"
                            strokeDasharray="4 4"
                            label={{
                                value: '1,00',
                                fill: 'var(--muted-foreground)',
                                fontSize: 11,
                                position: 'insideTopRight',
                            }}
                        />
                        <Line
                            dataKey="value"
                            type="monotone"
                            stroke="var(--primary)"
                            strokeWidth={2}
                            dot={false}
                            isAnimationActive={false}
                        />
                    </LineChart>
                </ResponsiveContainer>
            </div>

            <ul className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
                <LegendItem
                    label="P/VP de cada pregão"
                    color="var(--primary)"
                />
                <LegendItem
                    label="1,00 (preço = patrimônio por cota)"
                    color="var(--muted-foreground)"
                    dashed
                />
            </ul>

            {incomplete && (
                <p className="mt-1 text-xs text-muted-foreground">
                    O app guarda um fechamento por dia, então este período ainda
                    não está completo.
                </p>
            )}

            <table className="sr-only">
                <caption>
                    P/VP no último pregão de cada mês, nos últimos{' '}
                    {periodWords(period)}
                </caption>
                <thead>
                    <tr>
                        <th scope="col">Dia</th>
                        <th scope="col">P/VP</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.date}>
                            <th scope="row">{dayMonth(row.date)}</th>
                            <td>{ratio(row.priceToBook)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </>
    );
}

import {
    Area,
    AreaChart,
    CartesianGrid,
    ReferenceArea,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    ChartPeriod,
    axisTick,
    dateFromTime,
    periodWords,
    tickLabel,
    tooltipStyle,
} from '@/components/fund/chart-period';
import { brl, dayMonth, signedPercent } from '@/lib/format';
import {
    axisTicks,
    inPeriod,
    isIncomplete,
    monthlyLast,
    paddedDomain,
    periodAverage,
    periodChange,
    periodStart,
    toTime,
} from '@/lib/history';
import type { HistoryPoint, Period } from '@/lib/history';
import { cn } from '@/lib/utils';

const SHORT_HISTORY =
    'O app guarda um fechamento por dia, então este período ainda não está completo. A média usa só o que há.';

function plainBrl(value: number): string {
    return value.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

function axisBrl(value: number, span: number): string {
    return `R$ ${value.toLocaleString('pt-BR', {
        minimumFractionDigits: span < 10 ? 2 : 0,
        maximumFractionDigits: span < 10 ? 2 : 0,
    })}`;
}

function summary(
    first: string,
    current: string,
    period: Period,
    incomplete: boolean,
): string {
    const change = periodChange(first, current);
    const size = signedPercent(String(Math.abs(change))).replace('+', '');
    const verb =
        Math.abs(change) < 0.005
            ? 'ficou estável'
            : change > 0
              ? 'subiu'
              : 'caiu';
    const span = incomplete
        ? `no histórico guardado (últimos ${periodWords(period)} ainda incompletos)`
        : `em ${periodWords(period)}`;

    return `Preço ${verb}${verb === 'ficou estável' ? '' : ` ${size}`} ${span}, de ${brl(first)} para ${brl(current)}`;
}

export function PriceChart({
    history,
    price,
    average30d,
    target,
    period,
    onPeriodChange,
}: {
    history: HistoryPoint[];
    price: string | null;
    average30d: string | null;
    /** Só vem preenchido no modo "preço alvo". */
    target: string | null;
    period: Period;
    onPeriodChange: (period: Period) => void;
}) {
    const points = inPeriod(history, period);
    const lastDay =
        history.length > 0 ? history[history.length - 1].date : null;

    return (
        <section aria-labelledby="preco" className="surface p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 id="preco" className="text-lg font-semibold">
                    Preço
                </h2>
                <ChartPeriod
                    value={period}
                    onChange={onPeriodChange}
                    label="Período do gráfico de preço"
                />
            </div>

            {lastDay === null || points.length === 0 ? (
                <p className="mt-4 text-sm text-muted-foreground">
                    Ainda sem histórico de fechamentos. {SHORT_HISTORY}
                </p>
            ) : (
                <Body
                    points={points}
                    history={history}
                    lastDay={lastDay}
                    price={price}
                    average30d={average30d}
                    target={target}
                    period={period}
                />
            )}
        </section>
    );
}

function Body({
    points,
    history,
    lastDay,
    price,
    average30d,
    target,
    period,
}: {
    points: HistoryPoint[];
    history: HistoryPoint[];
    lastDay: string;
    price: string | null;
    average30d: string | null;
    target: string | null;
    period: Period;
}) {
    const start = toTime(periodStart(lastDay, period));
    const end = toTime(lastDay);
    const firstClose = points[0].close;
    const current = price ?? points[points.length - 1].close;
    const incomplete = isIncomplete(history, period);
    const change = periodChange(firstClose, current);
    const average = periodAverage(points);
    const targetValue = target === null ? null : Number(target);

    const data = points.map((point) => ({
        time: toTime(point.date),
        close: Number(point.close),
    }));
    const values = [
        ...data.map((d) => d.close),
        ...(average === null ? [] : [average]),
        ...(targetValue === null ? [] : [targetValue]),
    ];
    const domain = paddedDomain(values);
    const span = domain[1] - domain[0];
    const firstTime = data[0].time;

    const tableRows = monthlyLast(points);

    return (
        <>
            <p className="mt-2 num text-sm">
                <b
                    className={cn(
                        'font-semibold',
                        change < 0 && 'text-destructive',
                    )}
                >
                    {signedPercent(String(change))}
                </b>{' '}
                <span className="text-muted-foreground">
                    no período · de {brl(firstClose)} para {brl(current)}
                </span>
            </p>

            <div
                role="img"
                aria-label={summary(firstClose, current, period, incomplete)}
                className="mt-2 h-64 w-full"
            >
                <ResponsiveContainer width="100%" height="100%">
                    <AreaChart
                        data={data}
                        margin={{ top: 8, right: 8, bottom: 0, left: 0 }}
                        accessibilityLayer={false}
                    >
                        <defs>
                            <linearGradient
                                id="price-fill"
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop
                                    offset="0%"
                                    stopColor="var(--primary)"
                                    stopOpacity={0.3}
                                />
                                <stop
                                    offset="100%"
                                    stopColor="var(--primary)"
                                    stopOpacity={0}
                                />
                            </linearGradient>
                        </defs>
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
                            tickFormatter={(v: number) => axisBrl(v, span)}
                            tick={axisTick}
                            tickLine={false}
                            axisLine={false}
                            width={64}
                        />
                        <Tooltip
                            {...tooltipStyle}
                            labelFormatter={(t) =>
                                dayMonth(dateFromTime(Number(t)))
                            }
                            formatter={(v) => [
                                plainBrl(Number(v)),
                                'Fechamento',
                            ]}
                        />
                        {firstTime - start > 0 && incomplete && (
                            <ReferenceArea
                                x1={start}
                                x2={firstTime}
                                fill="var(--muted)"
                                fillOpacity={0.8}
                                label={{
                                    value: 'ainda sem histórico',
                                    fill: 'var(--muted-foreground)',
                                    fontSize: 12,
                                    position: 'insideTop',
                                }}
                            />
                        )}
                        {average !== null && (
                            <ReferenceLine
                                y={average}
                                stroke="var(--muted-foreground)"
                                strokeDasharray="4 4"
                            />
                        )}
                        {targetValue !== null && (
                            <ReferenceLine
                                y={targetValue}
                                stroke="var(--signal)"
                                label={{
                                    value: `alvo ${plainBrl(targetValue)}`,
                                    fill: 'var(--signal)',
                                    fontSize: 11,
                                    position: 'insideTopRight',
                                }}
                            />
                        )}
                        <Area
                            dataKey="close"
                            type="monotone"
                            stroke="var(--primary)"
                            strokeWidth={2}
                            fill="url(#price-fill)"
                            isAnimationActive={false}
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </div>

            <ul className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
                <LegendItem label="Fechamento" color="var(--primary)" />
                {average !== null && (
                    <LegendItem
                        label={`Média do período ${plainBrl(average)}`}
                        color="var(--muted-foreground)"
                        dashed
                    />
                )}
                {targetValue !== null && (
                    <LegendItem label="Seu alvo" color="var(--signal)" />
                )}
            </ul>

            <p className="mt-3 num text-sm">
                <span className="text-muted-foreground">
                    Média de 30 pregões:{' '}
                </span>
                <b className="font-semibold">
                    {average30d === null ? 'indisponível' : brl(average30d)}
                </b>
            </p>
            {incomplete && (
                <p className="mt-1 text-xs text-muted-foreground">
                    {SHORT_HISTORY}
                </p>
            )}

            <table className="sr-only">
                <caption>
                    Último fechamento de cada mês nos últimos{' '}
                    {periodWords(period)}
                </caption>
                <thead>
                    <tr>
                        <th scope="col">Dia</th>
                        <th scope="col">Fechamento</th>
                    </tr>
                </thead>
                <tbody>
                    {tableRows.map((row) => (
                        <tr key={row.date}>
                            <th scope="row">{dayMonth(row.date)}</th>
                            <td>{brl(row.close)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </>
    );
}

export function LegendItem({
    label,
    color,
    dashed = false,
}: {
    label: string;
    color: string;
    dashed?: boolean;
}) {
    return (
        <li className="inline-flex items-center gap-1.5">
            <span
                aria-hidden="true"
                className="inline-block w-4 border-t-2"
                style={{
                    borderColor: color,
                    borderTopStyle: dashed ? 'dashed' : 'solid',
                }}
            />
            {label}
        </li>
    );
}

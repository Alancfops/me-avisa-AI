import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { axisTick, tooltipStyle } from '@/components/fund/chart-period';
import { brl, percentFromFraction, shortMonthLabel } from '@/lib/format';

type Dividend = { month: string; perShare: string };

export function DividendsChart({
    dividends,
    dividendYield12m,
}: {
    dividends: Dividend[];
    dividendYield12m: string | null;
}) {
    const total = dividends.reduce((sum, d) => sum + Number(d.perShare), 0);
    const data = dividends.map((d) => ({
        label: shortMonthLabel(d.month),
        value: Number(d.perShare),
    }));
    const last = data.length - 1;

    return (
        <section aria-labelledby="rendimento" className="surface p-5">
            <div className="flex flex-wrap items-baseline justify-between gap-x-3">
                <h2 id="rendimento" className="text-lg font-semibold">
                    Rendimento por cota
                </h2>
                <span className="text-sm text-muted-foreground">
                    estimado ·{' '}
                    {dividends.length === 12 ? '12 meses' : 'meses com informe'}
                </span>
            </div>

            {dividends.length === 0 ? (
                <p className="mt-4 text-sm text-muted-foreground">
                    Sem informes da CVM para estimar.
                </p>
            ) : (
                <>
                    <p className="mt-2 num text-sm">
                        <b className="font-semibold">{brl(String(total))}</b>{' '}
                        <span className="text-muted-foreground">
                            em{' '}
                            {dividends.length === 12
                                ? '12 meses'
                                : `${dividends.length} ${dividends.length === 1 ? 'mês' : 'meses'}`}
                            {dividendYield12m !== null &&
                                ` · DY 12m ${percentFromFraction(dividendYield12m)}`}
                        </span>
                    </p>

                    <div
                        role="img"
                        aria-label={`Rendimento estimado por cota: ${brl(String(total))} em ${dividends.length} ${dividends.length === 1 ? 'mês' : 'meses'}; o último, de ${data[last].label}, foi ${brl(String(data[last].value))}`}
                        className="mt-2 h-44 w-full"
                    >
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart
                                data={data}
                                margin={{
                                    top: 8,
                                    right: 8,
                                    bottom: 0,
                                    left: 0,
                                }}
                                accessibilityLayer={false}
                            >
                                <CartesianGrid
                                    stroke="var(--border)"
                                    vertical={false}
                                />
                                <XAxis
                                    dataKey="label"
                                    tick={axisTick}
                                    tickLine={false}
                                    axisLine={false}
                                    interval="preserveStartEnd"
                                    minTickGap={8}
                                />
                                <YAxis
                                    tickCount={4}
                                    tickFormatter={(v: number) =>
                                        `R$ ${v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                    }
                                    tick={axisTick}
                                    tickLine={false}
                                    axisLine={false}
                                    width={64}
                                />
                                <Tooltip
                                    {...tooltipStyle}
                                    cursor={{
                                        fill: 'var(--muted)',
                                        opacity: 0.5,
                                    }}
                                    formatter={(v) => [
                                        brl(String(v)),
                                        'Rendimento estimado',
                                    ]}
                                />
                                <Bar
                                    dataKey="value"
                                    radius={[4, 4, 0, 0]}
                                    isAnimationActive={false}
                                >
                                    {data.map((entry, index) => (
                                        <Cell
                                            key={entry.label}
                                            fill="var(--primary)"
                                            fillOpacity={
                                                index === last ? 1 : 0.75
                                            }
                                        />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    </div>

                    <p className="mt-3 text-xs text-muted-foreground">
                        Estimado com o % pago no mês × VP por cota, do informe
                        da CVM. Mês de referência, não a data do pagamento. Pode
                        diferir do valor oficial.
                    </p>

                    <div className="sr-only">
                        <table>
                            <caption>
                                Rendimento estimado por cota, por mês de
                                referência
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">Mês</th>
                                    <th scope="col">Rendimento estimado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {dividends.map((d) => (
                                    <tr key={d.month}>
                                        <th scope="row">
                                            {shortMonthLabel(d.month)}
                                        </th>
                                        <td>{brl(d.perShare)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </section>
    );
}

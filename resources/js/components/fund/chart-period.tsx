import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { dayMonth, shortMonthLabel } from '@/lib/format';
import { PERIODS } from '@/lib/history';
import type { Period } from '@/lib/history';

/** Texto dos eixos e das grades: sempre tokens, nunca cor solta. */
export const axisTick = { fill: 'var(--muted-foreground)', fontSize: 11 };

export const tooltipStyle = {
    contentStyle: {
        background: 'var(--popover)',
        border: '1px solid var(--border)',
        borderRadius: 8,
        color: 'var(--popover-foreground)',
        fontSize: 13,
    },
    itemStyle: { color: 'var(--popover-foreground)' },
    labelStyle: { color: 'var(--muted-foreground)' },
};

export function dateFromTime(time: number): string {
    return new Date(time).toISOString().slice(0, 10);
}

/** Até 6 meses cabem dia/mês; em 12 meses o mês basta. */
export function tickLabel(time: number, period: Period): string {
    const date = dateFromTime(time);

    return period <= 6 ? dayMonth(date) : shortMonthLabel(date.slice(0, 7));
}

export function periodWords(period: Period): string {
    return period === 12 ? '12 meses' : `${period} meses`;
}

export function ChartPeriod({
    value,
    onChange,
    label,
}: {
    value: Period;
    onChange: (period: Period) => void;
    label: string;
}) {
    return (
        <ToggleGroup
            type="single"
            variant="outline"
            size="sm"
            value={String(value)}
            // Clicar no botão já marcado devolve '' — o período não pode ficar vazio.
            onValueChange={(next) => next && onChange(Number(next) as Period)}
            aria-label={label}
        >
            {PERIODS.map((months) => (
                <ToggleGroupItem
                    key={months}
                    value={String(months)}
                    aria-label={`${months} meses`}
                    className="min-h-8 min-w-11 px-3"
                >
                    {months}m
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}

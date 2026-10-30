import { Head, Link } from '@inertiajs/react';
import { Bell, Layers, Sparkles, Target, TrendingDown } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { RuleBadge } from '@/components/signal-badges';
import { brl } from '@/lib/format';
import { dayGroupKey, dayHeading, quoteMoment, timeOfDay } from '@/lib/market';
import type { Alert } from '@/types/fiis';

const icons: Record<string, LucideIcon> = {
    price_target: Target,
    buy_range: Layers,
    automatic: Sparkles,
    sharp_drop: TrendingDown,
};

function groupByDay(alerts: Alert[]): { key: string; alerts: Alert[] }[] {
    const groups = new Map<string, Alert[]>();

    for (const alert of alerts) {
        const key = dayGroupKey(alert.occurredAt);
        groups.set(key, [...(groups.get(key) ?? []), alert]);
    }

    return [...groups].map(([key, items]) => ({ key, alerts: items }));
}

function AlertItem({ alert }: { alert: Alert }) {
    const Icon = icons[alert.rule] ?? Bell;

    return (
        <li className="flex gap-3 p-4">
            <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary">
                <Icon className="size-4" aria-hidden="true" />
            </span>
            <div className="min-w-0 flex-1 space-y-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <Link
                        href={`/fundos/${alert.ticker}`}
                        className="inline-flex min-h-6 items-center font-display font-semibold text-primary underline-offset-4 hover:underline"
                    >
                        {alert.ticker}
                    </Link>
                    <RuleBadge rule={alert.rule} />
                    <time
                        dateTime={alert.occurredAt}
                        className="ml-auto num text-xs text-muted-foreground"
                    >
                        {timeOfDay(alert.occurredAt)}
                    </time>
                </div>
                <p className="text-sm wrap-anywhere">{alert.reason}</p>
                <p className="text-xs wrap-anywhere text-muted-foreground">
                    {brl(alert.price)} · Sua referência: {alert.reference} ·
                    Cotação de{' '}
                    <time dateTime={alert.quotedAt}>
                        {quoteMoment(
                            alert.quotedAt,
                            new Date(alert.occurredAt),
                        )}
                    </time>
                </p>
            </div>
        </li>
    );
}

export default function Alerts({ alerts }: { alerts: Alert[] }) {
    return (
        <>
            <Head title="Avisos" />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                <PageHeader
                    title="Avisos"
                    description="O que já bateu nas suas regras, do mais novo para o mais antigo."
                />

                {alerts.length === 0 ? (
                    <EmptyState
                        icon={Bell}
                        title="Nenhum aviso ainda. Assim que um fundo bater uma regra, ele aparece aqui."
                    />
                ) : (
                    groupByDay(alerts).map((group) => (
                        <section key={group.key} className="space-y-2">
                            <h2 className="text-sm font-semibold text-muted-foreground">
                                {dayHeading(group.alerts[0].occurredAt)}
                            </h2>
                            <ul className="divide-y surface">
                                {group.alerts.map((alert) => (
                                    <AlertItem
                                        key={`${alert.ticker}-${alert.rule}-${alert.occurredAt}`}
                                        alert={alert}
                                    />
                                ))}
                            </ul>
                        </section>
                    ))
                )}
            </div>
        </>
    );
}

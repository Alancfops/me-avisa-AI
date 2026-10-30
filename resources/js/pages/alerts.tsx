import { Head, Link } from '@inertiajs/react';
import { ruleLabel } from '@/components/rule-badges';
import { brl, dateTime } from '@/lib/format';

type Alert = {
    ticker: string;
    rule: string;
    price: string;
    reference: string;
    reason: string;
    occurredAt: string;
    quotedAt: string;
};

export default function Alerts({ alerts }: { alerts: Alert[] }) {
    return (
        <>
            <Head title="Avisos" />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-8">
                <h1 className="text-2xl font-semibold">Avisos</h1>

                {alerts.length === 0 ? (
                    <p className="text-muted-foreground">
                        Nenhum aviso ainda. Assim que um fundo bater uma regra,
                        ele aparece aqui.
                    </p>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {alerts.map((alert, index) => (
                            <li key={index} className="space-y-1 p-4">
                                <p>
                                    <Link
                                        href={`/fundos/${alert.ticker}`}
                                        className="font-semibold underline"
                                    >
                                        {alert.ticker}
                                    </Link>{' '}
                                    · {ruleLabel(alert.rule)} ·{' '}
                                    <span className="tabular-nums">
                                        {brl(alert.price)}
                                    </span>
                                </p>
                                <p>{alert.reason}</p>
                                <p className="text-sm text-muted-foreground">
                                    Sua referência: {alert.reference}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Aviso de{' '}
                                    <time dateTime={alert.occurredAt}>
                                        {dateTime(alert.occurredAt)}
                                    </time>
                                    , cotação de{' '}
                                    <time dateTime={alert.quotedAt}>
                                        {dateTime(alert.quotedAt)}
                                    </time>
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

Alerts.layout = {
    breadcrumbs: [{ title: 'Avisos', href: '/avisos' }],
};

import { Head } from '@inertiajs/react';
import { dateTime } from '@/lib/format';

type SourceStatus = {
    source: string;
    lastSuccessAt: string | null;
    errors: { message: string; occurredAt: string }[];
};

type Usage = {
    plan: string;
    limit: number;
    used: number;
    remaining: number;
};

const names: Record<string, string> = {
    brapi: 'Cotações (brapi)',
    cvm: 'Informes (CVM)',
};

export default function Health({
    sources,
    usage,
}: {
    sources: SourceStatus[];
    usage: Usage | null;
}) {
    return (
        <>
            <Head title="Saúde" />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-8">
                <h1 className="text-2xl font-semibold">Saúde</h1>

                {sources.map((status) => (
                    <section
                        key={status.source}
                        aria-labelledby={`fonte-${status.source}`}
                        className="space-y-2 rounded-xl border p-5"
                    >
                        <h2
                            id={`fonte-${status.source}`}
                            className="text-lg font-semibold"
                        >
                            {names[status.source] ?? status.source}
                        </h2>
                        <p>
                            Última atualização:{' '}
                            {status.lastSuccessAt === null
                                ? 'nenhuma ainda'
                                : dateTime(status.lastSuccessAt)}
                        </p>

                        {status.errors.length === 0 ? (
                            <p className="text-muted-foreground">
                                Nenhum erro recente.
                            </p>
                        ) : (
                            <ul className="space-y-1">
                                {status.errors.map((error, index) => (
                                    <li key={index}>
                                        <time
                                            dateTime={error.occurredAt}
                                            className="text-sm text-muted-foreground"
                                        >
                                            {dateTime(error.occurredAt)}
                                        </time>{' '}
                                        {error.message}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {status.source === 'brapi' && (
                            <p>
                                {usage === null
                                    ? 'Consumo da cota indisponível agora.'
                                    : `Consumo: ${usage.used.toLocaleString('pt-BR')} de ${usage.limit.toLocaleString('pt-BR')} requisições em 30 dias (plano ${usage.plan}), restam ${usage.remaining.toLocaleString('pt-BR')}.`}
                            </p>
                        )}
                    </section>
                ))}
            </div>
        </>
    );
}

Health.layout = {
    breadcrumbs: [{ title: 'Saúde', href: '/saude' }],
};

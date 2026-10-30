import { Head } from '@inertiajs/react';
import { Activity, CircleAlert, CircleCheck, CircleDashed } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { dateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SourceStatus, Usage } from '@/types/fiis';

const names: Record<string, string> = {
    brapi: 'Cotações (brapi)',
    cvm: 'Informes (CVM)',
};

function hasNewerError(status: SourceStatus): boolean {
    if (status.errors.length === 0) {
        return false;
    }

    if (status.lastSuccessAt === null) {
        return true;
    }

    const success = Date.parse(status.lastSuccessAt);

    return status.errors.some(
        (error) => Date.parse(error.occurredAt) > success,
    );
}

function SourceState({ status }: { status: SourceStatus }) {
    const failing = hasNewerError(status);
    const neverRan = status.lastSuccessAt === null && !failing;
    const Icon = failing ? CircleAlert : neverRan ? CircleDashed : CircleCheck;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                failing
                    ? 'bg-drop-soft text-destructive'
                    : 'bg-muted text-muted-foreground',
            )}
        >
            <Icon className="size-3.5 shrink-0" aria-hidden="true" />
            {failing
                ? 'Com erro desde a última atualização'
                : neverRan
                  ? 'Ainda não rodou'
                  : 'Sem erro desde a última atualização'}
        </span>
    );
}

function UsageInfo({ usage }: { usage: Usage | null }) {
    if (usage === null) {
        return (
            <p className="text-sm text-muted-foreground">
                Consumo da cota indisponível agora.
            </p>
        );
    }

    const percent =
        usage.limit > 0 ? Math.min((usage.used / usage.limit) * 100, 100) : 0;

    return (
        <div className="space-y-2">
            <p className="text-sm">
                <span className="num font-semibold">
                    {usage.used.toLocaleString('pt-BR')}
                </span>{' '}
                de{' '}
                <span className="num">
                    {usage.limit.toLocaleString('pt-BR')}
                </span>{' '}
                requisições em 30 dias (plano {usage.plan}), restam{' '}
                <span className="num">
                    {usage.remaining.toLocaleString('pt-BR')}
                </span>
                .
            </p>
            <div
                aria-hidden="true"
                className="h-2 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className="h-full rounded-full bg-primary"
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}

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

            <div className="mx-auto w-full max-w-3xl space-y-6">
                <PageHeader
                    title="Saúde"
                    description="De onde vêm os dados e quando cada fonte atualizou pela última vez."
                />

                {sources.length === 0 && (
                    <EmptyState
                        icon={Activity}
                        title="Nenhuma fonte de dados registrada."
                    />
                )}

                {sources.map((status) => (
                    <section
                        key={status.source}
                        aria-labelledby={`fonte-${status.source}`}
                        className="space-y-4 surface p-5"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h2
                                id={`fonte-${status.source}`}
                                className="text-lg font-semibold"
                            >
                                {names[status.source] ?? status.source}
                            </h2>
                            <SourceState status={status} />
                        </div>

                        <p className="text-sm">
                            <span className="text-muted-foreground">
                                Última atualização:{' '}
                            </span>
                            {status.lastSuccessAt === null ? (
                                'nenhuma ainda'
                            ) : (
                                <time
                                    dateTime={status.lastSuccessAt}
                                    className="num font-medium"
                                >
                                    {dateTime(status.lastSuccessAt)}
                                </time>
                            )}
                        </p>

                        {status.source === 'brapi' && (
                            <UsageInfo usage={usage} />
                        )}

                        {status.errors.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nenhum erro recente.
                            </p>
                        ) : (
                            <div className="space-y-2 border-t pt-4">
                                <h3 className="text-sm font-semibold">
                                    Erros recentes
                                </h3>
                                <ul className="space-y-2">
                                    {status.errors.map((error, index) => (
                                        <li
                                            key={`${error.occurredAt}-${index}`}
                                            className="text-sm"
                                        >
                                            <time
                                                dateTime={error.occurredAt}
                                                className="block num text-xs text-muted-foreground"
                                            >
                                                {dateTime(error.occurredAt)}
                                            </time>
                                            <span className="wrap-anywhere">
                                                {error.message}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </section>
                ))}
            </div>
        </>
    );
}

import { Head, Link, router, usePage } from '@inertiajs/react';
import { Database, SearchX } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { FundListFilters } from '@/components/fund/fund-list-filters';
import { FundListTable } from '@/components/fund/fund-list-table';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dayMonth } from '@/lib/format';
import {
    DEFAULT_SORT,
    filterFunds,
    readFilters,
    sortFunds,
    writeFilters,
} from '@/lib/fund-list';
import type { BaseFilter, KindFilter, SortKey } from '@/lib/fund-list';
import type { FundListItem } from '@/types/fiis';

const URL_DELAY_MS = 300;

export default function FundIndex({
    funds,
    closesOn,
}: {
    funds: FundListItem[];
    closesOn: string | null;
}) {
    const { url } = usePage();
    const initial = readFilters(url.split('?')[1] ?? '');
    const [query, setQuery] = useState(initial.query);
    const [kind, setKind] = useState<KindFilter>(initial.kind);
    const [base, setBase] = useState<BaseFilter>(initial.base);
    const [sort, setSort] = useState<SortKey>(initial.sort);

    // Os filtros vão para a URL (sem nova entrada no histórico): voltar de uma
    // página de fundo reabre a lista como estava.
    useEffect(() => {
        const timer = window.setTimeout(() => {
            const search = writeFilters({ query, kind, base, sort });

            if (search === window.location.search) {
                return;
            }

            router.replace({
                url: `${window.location.pathname}${search}`,
                preserveState: true,
                preserveScroll: true,
                props: (props) => props,
            });
        }, URL_DELAY_MS);

        return () => window.clearTimeout(timer);
    }, [query, kind, base, sort]);

    const visible = sortFunds(filterFunds(funds, { query, kind, base }), sort);
    const filtered = query.trim() !== '' || kind !== 'all' || base !== 'all';

    function clear() {
        setQuery('');
        setKind('all');
        setBase('all');
        setSort(DEFAULT_SORT);
        // O botão some junto com o aviso; o foco volta para o primeiro filtro.
        document.getElementById('busca-fundos')?.focus();
    }

    return (
        <>
            <Head title="Todos os FIIs" />

            <div className="mx-auto w-full max-w-6xl space-y-6">
                <PageHeader
                    title="Todos os FIIs"
                    description={
                        funds.length === 0
                            ? undefined
                            : `${funds.length} ${funds.length === 1 ? 'fundo negociado' : 'fundos negociados'}${
                                  closesOn
                                      ? ` · preços do fechamento de ${dayMonth(closesOn)} (B3)`
                                      : ''
                              } · VP e DY dos informes da CVM`
                    }
                />

                {funds.length === 0 ? (
                    <EmptyState
                        icon={Database}
                        title="A lista de fundos ainda não foi carregada. Os fechamentos da B3 e os informes da CVM entram sozinhos; se demorar, veja o que houve em Saúde."
                    >
                        <Button asChild variant="outline">
                            <Link href="/saude">Ver Saúde</Link>
                        </Button>
                    </EmptyState>
                ) : (
                    <>
                        <FundListFilters
                            query={query}
                            kind={kind}
                            base={base}
                            sort={sort}
                            onQuery={setQuery}
                            onKind={setKind}
                            onBase={setBase}
                            onSort={setSort}
                        />

                        <div className="overflow-hidden surface">
                            {visible.length === 0 ? (
                                <div className="flex flex-col items-center gap-3 p-8 text-center">
                                    <SearchX
                                        className="size-6 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <p className="text-muted-foreground">
                                        Nenhum fundo com esses filtros.
                                    </p>
                                    {filtered && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={clear}
                                        >
                                            Limpar filtros
                                        </Button>
                                    )}
                                </div>
                            ) : (
                                <FundListTable
                                    funds={visible}
                                    sort={sort}
                                    closesOn={closesOn}
                                />
                            )}

                            <div className="flex flex-wrap justify-between gap-x-6 gap-y-1 border-t px-4 py-3 text-sm text-muted-foreground">
                                <p aria-live="polite" className="num">
                                    Mostrando {visible.length} de {funds.length}
                                </p>
                                <p>
                                    P/VP em verde: abaixo de 1,0 · Tipo pela
                                    carteira informada à CVM · Segmento como a
                                    CVM informa
                                </p>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

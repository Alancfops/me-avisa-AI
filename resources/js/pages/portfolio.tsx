import { Head } from '@inertiajs/react';
import { Plus, Wallet } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { AddHoldingDialog } from '@/components/portfolio/add-holding-dialog';
import { HoldingRow } from '@/components/portfolio/holding-row';
import { Button } from '@/components/ui/button';
import { brl } from '@/lib/format';
import { ESTIMATE_NOTE } from '@/lib/portfolio';
import type {
    PortfolioRow,
    PortfolioSearch,
    PortfolioTotals,
} from '@/types/fiis';

function funds(count: number): string {
    return count === 1 ? '1 fundo' : `${count} fundos`;
}

function LeftOut({ totals }: { totals: PortfolioTotals }) {
    const notes: string[] = [];

    if (totals.withoutPrice > 0) {
        notes.push(
            `${funds(totals.withoutPrice)} sem preço ${totals.withoutPrice === 1 ? 'ficou' : 'ficaram'} fora do valor total.`,
        );
    }

    if (totals.withoutDividends > 0) {
        notes.push(
            `${funds(totals.withoutDividends)} sem rendimento informado ${totals.withoutDividends === 1 ? 'ficou' : 'ficaram'} fora do total.`,
        );
    }

    return notes.length === 0 ? null : (
        <div className="space-y-0.5 text-sm text-muted-foreground">
            {notes.map((note) => (
                <p key={note}>{note}</p>
            ))}
        </div>
    );
}

function Total({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="surface p-5">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-1 num text-2xl font-semibold">
                {value === null ? '—' : brl(value)}
            </p>
        </div>
    );
}

export default function Portfolio({
    rows,
    totals,
    search,
}: {
    rows: PortfolioRow[];
    totals: PortfolioTotals;
    search: PortfolioSearch;
}) {
    const [adding, setAdding] = useState(false);

    return (
        <>
            <Head title="Minha carteira" />

            <div className="mx-auto w-full max-w-6xl space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <PageHeader
                        title="Minha carteira"
                        description={ESTIMATE_NOTE}
                    />
                    <Button onClick={() => setAdding(true)}>
                        <Plus aria-hidden="true" />
                        Adicionar FII
                    </Button>
                </div>

                {rows.length === 0 ? (
                    <EmptyState
                        icon={Wallet}
                        title="Sua carteira está vazia. Adicione um FII com a quantidade de cotas para ver quanto ele rende por mês."
                    />
                ) : (
                    <>
                        <section
                            aria-label="Totais da carteira"
                            className="grid gap-4 sm:grid-cols-3"
                        >
                            <Total
                                label="Valor da carteira"
                                value={totals.value}
                            />
                            <Total
                                label="Por mês, pelo último rendimento"
                                value={totals.lastIncome}
                            />
                            <Total
                                label="Por mês, pela média de 12 meses"
                                value={totals.averageIncome}
                            />
                        </section>
                        <LeftOut totals={totals} />

                        <div className="overflow-x-auto surface">
                            <table className="w-full min-w-[44rem] text-sm">
                                <caption className="sr-only">
                                    Fundos da carteira, do maior valor para o
                                    menor
                                </caption>
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="p-3 font-medium"
                                        >
                                            Fundo
                                        </th>
                                        <th
                                            scope="col"
                                            className="p-3 font-medium"
                                        >
                                            Cotas
                                        </th>
                                        <th
                                            scope="col"
                                            className="p-3 font-medium"
                                        >
                                            Preço
                                        </th>
                                        <th
                                            scope="col"
                                            className="p-3 font-medium"
                                        >
                                            Valor
                                        </th>
                                        <th
                                            scope="col"
                                            className="p-3 font-medium"
                                        >
                                            Rendimento por mês
                                        </th>
                                        <th scope="col" className="p-3">
                                            <span className="sr-only">
                                                Ações
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row) => (
                                        <HoldingRow
                                            key={`${row.ticker}-${row.shares}`}
                                            row={row}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>

            <AddHoldingDialog
                open={adding}
                onOpenChange={setAdding}
                search={search}
            />
        </>
    );
}

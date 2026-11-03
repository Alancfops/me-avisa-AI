import { Head, Link } from '@inertiajs/react';
import type { FundListItem } from '@/types/fiis';

// Versão mínima: a lista com filtros, ordenação e favoritar fica na tela da lista (Task 5).
export default function FundIndex({
    funds,
    closesOn,
}: {
    funds: FundListItem[];
    closesOn: string | null;
}) {
    return (
        <>
            <Head title="Fundos" />

            <div className="mx-auto w-full max-w-6xl space-y-4">
                <h1 className="text-xl font-semibold">Fundos</h1>
                {closesOn && (
                    <p className="text-sm text-muted-foreground">
                        Fechamento de {closesOn}
                    </p>
                )}
                <ul className="divide-y">
                    {funds.map((fund) => (
                        <li key={fund.ticker} className="py-2">
                            <Link href={`/fundos/${fund.ticker}`}>
                                {fund.ticker} · {fund.name}
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

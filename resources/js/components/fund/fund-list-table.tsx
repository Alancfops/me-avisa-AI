import { Link } from '@inertiajs/react';
import { FavoriteButton } from '@/components/fund/favorite-button';
import { dayMonth, percentFromFraction, ratio, brl } from '@/lib/format';
import { KIND_LABELS } from '@/lib/fund-list';
import type { SortKey } from '@/lib/fund-list';
import { cn } from '@/lib/utils';
import type { FundKind, FundListItem } from '@/types/fiis';

/** Dado ausente aparece como traço, e o leitor de tela lê "sem dado". */
function Missing() {
    return (
        <>
            <span aria-hidden="true">—</span>
            <span className="sr-only">sem dado</span>
        </>
    );
}

function KindChip({ kind }: { kind: FundKind | null }) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap text-muted-foreground',
                kind === 'tijolo' && 'bg-primary-soft text-foreground',
            )}
        >
            {kind === null ? 'Sem dados' : KIND_LABELS[kind]}
        </span>
    );
}

const HEAD = 'px-1 py-3 text-xs font-semibold text-muted-foreground sm:px-4';
const CELL = 'px-1 py-3 align-middle sm:px-4';

/** Coluna que cada ordenação destaca; patrimônio não tem coluna. */
function ariaSort(
    column: 'fund' | 'priceToBook' | 'dividendYield',
    sort: SortKey,
): 'ascending' | 'descending' | undefined {
    if (column === 'fund' && sort === 'ticker') {
        return 'ascending';
    }

    if (column === 'priceToBook' && sort === 'priceToBook') {
        return 'ascending';
    }

    if (column === 'dividendYield' && sort === 'dividendYield') {
        return 'descending';
    }

    return undefined;
}

export function FundListTable({
    funds,
    sort,
    closesOn,
}: {
    funds: FundListItem[];
    sort: SortKey;
    closesOn: string | null;
}) {
    return (
        <div
            role="region"
            aria-label="Tabela de fundos"
            tabIndex={0}
            className="overflow-x-auto rounded-xl focus-visible:ring-2 focus-visible:ring-ring"
        >
            <table className="w-full border-collapse text-sm">
                <caption className="sr-only">
                    Fundos negociados, com preço de fechamento, P/VP e DY dos
                    últimos 12 meses
                </caption>
                <thead>
                    <tr className="border-b">
                        <th
                            scope="col"
                            aria-sort={ariaSort('fund', sort)}
                            className={cn(HEAD, 'text-left')}
                        >
                            Fundo
                        </th>
                        <th
                            scope="col"
                            className={cn(
                                HEAD,
                                'hidden text-left md:table-cell',
                            )}
                        >
                            Tipo
                        </th>
                        <th
                            scope="col"
                            className={cn(
                                HEAD,
                                'hidden text-left md:table-cell',
                            )}
                        >
                            Segmento
                        </th>
                        <th scope="col" className={cn(HEAD, 'text-right')}>
                            Preço
                        </th>
                        <th
                            scope="col"
                            aria-sort={ariaSort('priceToBook', sort)}
                            className={cn(HEAD, 'text-right')}
                        >
                            P/VP
                        </th>
                        <th
                            scope="col"
                            aria-sort={ariaSort('dividendYield', sort)}
                            className={cn(HEAD, 'text-right')}
                        >
                            DY 12m
                        </th>
                        <th scope="col" className={cn(HEAD, 'text-right')}>
                            <span className="sr-only">Favorito</span>
                        </th>
                    </tr>
                </thead>
                <tbody className="num">
                    {funds.map((fund) => {
                        const below =
                            fund.priceToBook !== null &&
                            Number(fund.priceToBook) < 1;

                        return (
                            <tr
                                key={fund.ticker}
                                className="border-b last:border-b-0 hover:bg-primary-soft"
                            >
                                <th
                                    scope="row"
                                    className={cn(
                                        CELL,
                                        'text-left font-normal md:min-w-64',
                                    )}
                                >
                                    <Link
                                        href={`/fundos/${fund.ticker}`}
                                        className="inline-block min-h-6 rounded-sm font-display text-[15px] font-semibold focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        {fund.ticker}
                                    </Link>
                                    <span className="block max-w-40 text-xs font-normal [font-weight:400] break-words text-muted-foreground sm:max-w-64">
                                        {fund.name}
                                    </span>
                                </th>
                                <td
                                    className={cn(CELL, 'hidden md:table-cell')}
                                >
                                    <KindChip kind={fund.kind} />
                                </td>
                                <td
                                    className={cn(
                                        CELL,
                                        'hidden text-muted-foreground md:table-cell',
                                    )}
                                >
                                    {fund.segment ?? <Missing />}
                                </td>
                                <td className={cn(CELL, 'text-right')}>
                                    {fund.close === null ? (
                                        <Missing />
                                    ) : (
                                        <>
                                            {brl(fund.close)}
                                            {fund.closeDay !== null &&
                                                fund.closeDay !== closesOn && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        fechamento de{' '}
                                                        {dayMonth(
                                                            fund.closeDay,
                                                        )}
                                                    </span>
                                                )}
                                        </>
                                    )}
                                </td>
                                <td
                                    className={cn(
                                        CELL,
                                        'text-right',
                                        below && 'font-semibold text-signal',
                                    )}
                                >
                                    {fund.priceToBook === null ? (
                                        <Missing />
                                    ) : (
                                        <>
                                            {ratio(fund.priceToBook)}
                                            {below && (
                                                <span className="sr-only">
                                                    , abaixo de 1,0
                                                </span>
                                            )}
                                        </>
                                    )}
                                </td>
                                <td className={cn(CELL, 'text-right')}>
                                    {fund.dividendYield12m === null ? (
                                        <Missing />
                                    ) : (
                                        percentFromFraction(
                                            fund.dividendYield12m,
                                        )
                                    )}
                                </td>
                                <td className={cn(CELL, 'text-right')}>
                                    <FavoriteButton
                                        ticker={fund.ticker}
                                        favorite={fund.favorite}
                                        compact
                                    />
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

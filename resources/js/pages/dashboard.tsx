import { Head, Link } from '@inertiajs/react';
import { CloudOff, Plus, Star } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { FavoriteDialog } from '@/components/favorite-dialog';
import { FundCard } from '@/components/fund-card';
import { QuoteTime } from '@/components/quote-info';
import { Button } from '@/components/ui/button';
import { longDate } from '@/lib/market';
import { hasGoodPrice, hasSharpDrop, sortForDashboard } from '@/lib/rules';
import { cn } from '@/lib/utils';
import type { Fund, Search } from '@/types/fiis';

function Stat({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone?: 'signal' | 'drop';
}) {
    return (
        <div className="surface p-5">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'mt-1 num text-3xl font-semibold',
                    tone === 'signal' && 'text-signal',
                    tone === 'drop' && 'text-destructive',
                )}
            >
                {value}
            </dd>
        </div>
    );
}

export default function Dashboard({
    favorites,
    search,
}: {
    favorites: Fund[];
    search: Search;
}) {
    // Com ?q= na URL, a pessoa estava buscando: o diálogo reabre onde parou.
    const [dialogOpen, setDialogOpen] = useState(search.query !== '');
    const [opener, setOpener] = useState<HTMLElement | null>(null);
    const openDialog = (event: React.MouseEvent<HTMLElement>) => {
        setOpener(event.currentTarget);
        setDialogOpen(true);
    };
    const quoted = favorites.filter((fund) => fund.price !== null);
    const newestQuote = quoted
        .map((fund) => fund.quotedAt)
        .filter((quotedAt) => quotedAt !== null)
        .sort((a, b) => Date.parse(b) - Date.parse(a))[0];

    return (
        <>
            <Head title="Painel" />

            <div className="mx-auto w-full max-w-6xl space-y-6 md:space-y-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="space-y-1">
                        <p className="text-sm text-muted-foreground">
                            {longDate()}
                        </p>
                        <h1 className="text-3xl font-semibold md:text-4xl">
                            Seus fundos
                        </h1>
                        {newestQuote && <QuoteTime quotedAt={newestQuote} />}
                    </div>
                    <Button onClick={openDialog}>
                        <Plus aria-hidden="true" />
                        Favoritar FII
                    </Button>
                </div>

                {favorites.length === 0 ? (
                    <EmptyState
                        icon={Star}
                        title="Nenhum favorito ainda. Favorite um fundo para acompanhar o preço e escolher quando ser avisado."
                    >
                        <Button onClick={openDialog}>
                            <Plus aria-hidden="true" />
                            Favoritar FII
                        </Button>
                    </EmptyState>
                ) : (
                    <>
                        <dl className="grid gap-4 sm:grid-cols-3">
                            <Stat label="Favoritos" value={favorites.length} />
                            <Stat
                                label="Com preço bom agora"
                                tone="signal"
                                value={
                                    quoted.filter((fund) =>
                                        hasGoodPrice(fund.matchedRules),
                                    ).length
                                }
                            />
                            <Stat
                                label="Com queda forte"
                                tone="drop"
                                value={
                                    quoted.filter((fund) =>
                                        hasSharpDrop(fund.matchedRules),
                                    ).length
                                }
                            />
                        </dl>

                        {quoted.length === 0 && (
                            <div className="flex items-start gap-3 surface p-4 text-sm">
                                <CloudOff
                                    className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <p>
                                    Ainda não temos cotação dos seus fundos.
                                    Elas atualizam a cada 30 minutos no pregão;
                                    se demorar, veja o que houve em{' '}
                                    <Link
                                        href="/saude"
                                        className="text-primary underline"
                                    >
                                        Saúde
                                    </Link>
                                    .
                                </p>
                            </div>
                        )}

                        <ul className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {sortForDashboard(favorites).map((fund) => (
                                <li key={fund.ticker}>
                                    <FundCard fund={fund} />
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </div>

            <FavoriteDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                search={search}
                returnFocusTo={opener}
            />
        </>
    );
}

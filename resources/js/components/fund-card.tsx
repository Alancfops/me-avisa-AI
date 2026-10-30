import { Link } from '@inertiajs/react';
import { DayChange, QuoteTime } from '@/components/quote-info';
import { SignalBadges } from '@/components/signal-badges';
import { brl, percentFromFraction, ratio } from '@/lib/format';
import { hasGoodPrice, hasSharpDrop } from '@/lib/rules';
import { cn } from '@/lib/utils';
import type { Fund } from '@/types/fiis';

function Mini({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="num font-medium">{value}</dd>
        </div>
    );
}

export function FundCard({ fund }: { fund: Fund }) {
    const hasQuote = fund.price !== null && fund.quotedAt !== null;

    return (
        <article
            className={cn(
                'relative flex h-full flex-col gap-4 surface p-5 focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2 focus-within:ring-offset-background motion-safe:transition-transform motion-safe:hover:-translate-y-0.5',
                hasQuote && hasGoodPrice(fund.matchedRules) && 'surface-signal',
            )}
        >
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <h2 className="text-lg font-semibold">
                        <Link
                            href={`/fundos/${fund.ticker}`}
                            className="stretched-link rounded-xl outline-none after:absolute after:inset-0 after:rounded-xl"
                        >
                            {fund.ticker}
                        </Link>
                    </h2>
                    {fund.name && (
                        <p className="text-sm wrap-anywhere text-muted-foreground">
                            {fund.name}
                        </p>
                    )}
                </div>
                <SignalBadges
                    rules={fund.matchedRules}
                    hasQuote={fund.price !== null}
                />
            </div>

            {fund.price === null || fund.quotedAt === null ? (
                <p className="text-sm text-muted-foreground">
                    Cotação indisponível por enquanto.
                </p>
            ) : (
                <div className="space-y-0.5">
                    <p className="num text-3xl font-semibold">
                        {brl(fund.price)}
                    </p>
                    <DayChange
                        changePercent={fund.changePercent}
                        quotedAt={fund.quotedAt}
                        sharpDrop={hasSharpDrop(fund.matchedRules)}
                    />
                    <QuoteTime quotedAt={fund.quotedAt} />
                </div>
            )}

            <dl className="mt-auto grid grid-cols-3 gap-2 border-t pt-3 text-xs">
                <Mini
                    label="Média 30d"
                    value={
                        fund.average30d === null
                            ? 'Indisponível'
                            : brl(fund.average30d)
                    }
                />
                <Mini
                    label="P/VP"
                    value={
                        fund.priceToBook === null
                            ? 'Indisponível'
                            : ratio(fund.priceToBook)
                    }
                />
                <Mini
                    label="DY 12m"
                    value={
                        fund.dividendYield12m === null
                            ? 'Indisponível'
                            : percentFromFraction(fund.dividendYield12m)
                    }
                />
            </dl>
        </article>
    );
}

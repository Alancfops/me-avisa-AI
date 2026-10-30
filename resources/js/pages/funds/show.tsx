import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, Minus, TriangleAlert } from 'lucide-react';
import { DayChange, QuoteTime } from '@/components/quote-info';
import { RemoveFavorite } from '@/components/remove-favorite';
import { SignalBadges } from '@/components/signal-badges';
import { WhenToAlert } from '@/components/when-to-alert';
import { brl, monthLabel, percentFromFraction, ratio } from '@/lib/format';
import { hasSharpDrop, ruleName } from '@/lib/rules';
import type { Fund, RuleStatus, Settings } from '@/types/fiis';

/** Abaixo disso a regra automática usa P/VP 1,0 no lugar da mediana (alert.md). */
const MIN_DAYS_FOR_MEDIAN = 60;

function Info({
    label,
    value,
    detail,
}: {
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="num text-lg font-semibold">{value}</dd>
            {detail && (
                <dd className="text-xs text-muted-foreground">{detail}</dd>
            )}
        </div>
    );
}

function RuleLine({ status }: { status: RuleStatus }) {
    const Icon = status.matched
        ? Check
        : status.unavailableReason
          ? TriangleAlert
          : Minus;

    return (
        <li className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
            <Icon
                className={
                    status.matched
                        ? 'mt-0.5 size-4 shrink-0 text-signal'
                        : 'mt-0.5 size-4 shrink-0 text-muted-foreground'
                }
                aria-hidden="true"
            />
            <div className="min-w-0 text-sm">
                <p className="font-medium">{ruleName(status.rule)}</p>
                <p className="wrap-anywhere text-muted-foreground">
                    {status.unavailableReason ??
                        status.reason ??
                        'Não bateu agora.'}
                </p>
                {status.note && (
                    <p className="text-xs text-muted-foreground">
                        {status.note}
                    </p>
                )}
            </div>
        </li>
    );
}

export default function FundShow({
    fund,
    settings,
    rules,
}: {
    fund: Fund;
    settings: Settings;
    rules: RuleStatus[];
}) {
    const shortHistory = fund.priceToBookDays < MIN_DAYS_FOR_MEDIAN;

    return (
        <>
            <Head title={fund.ticker} />

            <div className="mx-auto w-full max-w-6xl space-y-6">
                <Link
                    href="/"
                    className="inline-flex min-h-6 items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" aria-hidden="true" />
                    Voltar para o painel
                </Link>

                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="min-w-0 space-y-1">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-3xl font-semibold md:text-4xl">
                                {fund.ticker}
                            </h1>
                            <SignalBadges
                                rules={fund.matchedRules}
                                hasQuote={fund.price !== null}
                            />
                        </div>
                        {fund.name && (
                            <p className="wrap-anywhere text-muted-foreground">
                                {fund.name}
                            </p>
                        )}
                    </div>

                    {fund.price === null || fund.quotedAt === null ? (
                        <p className="text-muted-foreground">
                            Cotação indisponível por enquanto.
                        </p>
                    ) : (
                        <div className="space-y-0.5 sm:text-right">
                            <p className="num text-4xl font-semibold">
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
                </header>

                {/*
                 * "Quando me avisar" vem antes no DOM: no celular fica logo depois
                 * do preço, e em lg a grade o põe à direita sem usar `order`.
                 */}
                <div className="grid items-start gap-6 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-start-3 lg:row-start-1">
                        <WhenToAlert
                            ticker={fund.ticker}
                            settings={settings}
                            shortHistory={shortHistory}
                        />
                        <RemoveFavorite ticker={fund.ticker} />
                    </div>

                    <div className="space-y-6 lg:col-span-2 lg:col-start-1 lg:row-start-1">
                        <section
                            aria-labelledby="dados"
                            className="surface p-5"
                        >
                            <h2 id="dados" className="text-lg font-semibold">
                                Dados do fundo
                            </h2>
                            <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                                <Info
                                    label="Média de 30 pregões"
                                    value={
                                        fund.average30d === null
                                            ? 'Indisponível'
                                            : brl(fund.average30d)
                                    }
                                />
                                <Info
                                    label="P/VP"
                                    value={
                                        fund.priceToBook === null
                                            ? 'Indisponível'
                                            : ratio(fund.priceToBook)
                                    }
                                    detail={
                                        fund.bookValueMonth
                                            ? `VP de ${monthLabel(fund.bookValueMonth)}`
                                            : 'Sem informe da CVM para calcular.'
                                    }
                                />
                                {!shortHistory &&
                                    fund.medianPriceToBook !== null && (
                                        <Info
                                            label="Mediana do P/VP em 12 meses"
                                            value={ratio(
                                                fund.medianPriceToBook,
                                            )}
                                        />
                                    )}
                                <Info
                                    label="DY 12 meses"
                                    value={
                                        fund.dividendYield12m === null
                                            ? 'Indisponível'
                                            : percentFromFraction(
                                                  fund.dividendYield12m,
                                              )
                                    }
                                    detail="Soma dos informes mensais da CVM."
                                />
                            </dl>
                        </section>

                        {rules.length > 0 && (
                            <section
                                aria-labelledby="regras"
                                className="surface p-5"
                            >
                                <h2
                                    id="regras"
                                    className="text-lg font-semibold"
                                >
                                    Situação das regras
                                </h2>
                                <ul className="mt-4 divide-y">
                                    {rules.map((status) => (
                                        <RuleLine
                                            key={status.rule}
                                            status={status}
                                        />
                                    ))}
                                </ul>
                            </section>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

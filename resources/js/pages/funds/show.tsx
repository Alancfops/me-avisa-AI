import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Check, Minus, TriangleAlert } from 'lucide-react';
import {
    DecisionSummary,
    MIN_DAYS_FOR_MEDIAN,
} from '@/components/fund/decision-summary';
import { CeilingPrice } from '@/components/fund/ceiling-price';
import { DividendsChart } from '@/components/fund/dividends-chart';
import { FavoriteButton } from '@/components/fund/favorite-button';
import { FundProfile } from '@/components/fund/fund-profile';
import { PriceChart } from '@/components/fund/price-chart';
import { PriceToBookChart } from '@/components/fund/price-to-book-chart';
import { DayChange, QuoteTime } from '@/components/quote-info';
import { RemoveFavorite } from '@/components/remove-favorite';
import { SignalBadges } from '@/components/signal-badges';
import { WhenToAlert } from '@/components/when-to-alert';
import type { SuggestedTarget } from '@/components/when-to-alert';
import { brl, clockTime, dayMonth } from '@/lib/format';
import type { Period } from '@/lib/history';
import { hasSharpDrop, ruleName } from '@/lib/rules';
import type { Fund, RuleStatus, Settings } from '@/types/fiis';

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
    settings: Settings | null;
    rules: RuleStatus[];
}) {
    const shortHistory = fund.priceToBookDays < MIN_DAYS_FOR_MEDIAN;
    const [period, setPeriod] = useState<Period>(6);
    const history = fund.history ?? [];
    const segment = fund.profile?.segment ?? null;
    const [suggestion, setSuggestion] = useState<SuggestedTarget | null>(null);
    // Quem não é favorito só tem o fechamento da B3, sempre com a data.
    const closeOnly = !fund.favorite && fund.price !== null;
    const closeNote =
        fund.closeDay === null
            ? 'fechamento da B3'
            : `fechamento de ${dayMonth(fund.closeDay)} (B3)`;

    return (
        <>
            <Head title={fund.ticker} />

            <div className="mx-auto w-full max-w-6xl space-y-6">
                <Link
                    href={fund.favorite ? '/' : '/fundos'}
                    className="inline-flex min-h-6 items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" aria-hidden="true" />
                    {fund.favorite
                        ? 'Voltar para o painel'
                        : 'Voltar para todos os FIIs'}
                </Link>

                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="min-w-0 flex-1 basis-80 space-y-1">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-3xl font-semibold md:text-4xl">
                                {fund.ticker}
                            </h1>
                            {fund.favorite && (
                                <SignalBadges
                                    rules={fund.matchedRules}
                                    hasQuote={fund.price !== null}
                                />
                            )}
                        </div>
                        {(fund.name || segment) && (
                            <p className="wrap-anywhere text-muted-foreground">
                                {[fund.name, segment]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        )}
                    </div>

                    {closeOnly && fund.price !== null ? (
                        <div className="shrink-0 space-y-0.5 sm:text-right">
                            <p className="num text-4xl font-semibold">
                                {brl(fund.price)}
                            </p>
                            <p className="num text-sm text-muted-foreground">
                                {fund.closeDay === null
                                    ? 'Fechamento da B3'
                                    : `Fechamento de ${dayMonth(fund.closeDay)} (B3)`}
                            </p>
                        </div>
                    ) : !fund.favorite && fund.price === null ? (
                        <p className="text-muted-foreground">
                            Sem preço de fechamento recente na B3.
                        </p>
                    ) : fund.price === null || fund.quotedAt === null ? (
                        <p className="text-muted-foreground">
                            Cotação indisponível por enquanto.
                        </p>
                    ) : (
                        <div className="shrink-0 space-y-0.5 sm:text-right">
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
                 * do preço. Em lg a grade põe o conteúdo à esquerda e a lateral à
                 * direita (Sobre, Quando me avisar, Remover) sem usar `order`.
                 */}
                <div className="grid items-start gap-6 lg:grid-cols-3 lg:grid-rows-[auto_auto_1fr]">
                    <div className="min-w-0 space-y-6 lg:col-start-3 lg:row-start-2">
                        {settings ? (
                            <WhenToAlert
                                ticker={fund.ticker}
                                settings={settings}
                                shortHistory={shortHistory}
                                suggestedTarget={suggestion}
                            />
                        ) : (
                            <section
                                aria-labelledby="acompanhar"
                                className="space-y-3 surface p-5"
                            >
                                <h2
                                    id="acompanhar"
                                    className="text-lg font-semibold"
                                >
                                    Acompanhar este fundo
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Ao favoritar, você escolhe quando ser
                                    avisado e passa a ver a cotação a cada 30
                                    minutos.
                                </p>
                                <FavoriteButton
                                    ticker={fund.ticker}
                                    favorite={fund.favorite}
                                    focusAlert
                                    className="w-full"
                                />
                            </section>
                        )}

                        <CeilingPrice
                            dividends={fund.dividends ?? []}
                            price={fund.price}
                            priceNote={
                                closeOnly || fund.quotedAt === null
                                    ? closeNote
                                    : `cotação de ${clockTime(fund.quotedAt)}`
                            }
                            onUseAsTarget={
                                settings
                                    ? (value) =>
                                          setSuggestion((current) => ({
                                              value,
                                              id: (current?.id ?? 0) + 1,
                                          }))
                                    : undefined
                            }
                        />
                    </div>

                    <div className="min-w-0 space-y-6 lg:col-span-2 lg:col-start-1 lg:row-span-3 lg:row-start-1">
                        <DecisionSummary fund={fund} />

                        <PriceChart
                            history={history}
                            price={fund.price}
                            average30d={fund.average30d}
                            target={
                                settings?.mode === 'target'
                                    ? settings.target
                                    : null
                            }
                            period={period}
                            onPeriodChange={setPeriod}
                        />

                        <PriceToBookChart
                            history={history}
                            period={period}
                            onPeriodChange={setPeriod}
                        />

                        <DividendsChart
                            dividends={fund.dividends ?? []}
                            dividendYield12m={fund.dividendYield12m}
                        />

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

                    <div className="min-w-0 lg:col-start-3 lg:row-start-1">
                        <FundProfile profile={fund.profile ?? null} />
                    </div>

                    <div className="min-w-0 lg:col-start-3 lg:row-start-3">
                        {fund.favorite && (
                            <RemoveFavorite ticker={fund.ticker} />
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

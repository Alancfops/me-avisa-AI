import {
    brl,
    dayMonth,
    ratio,
    shortMonthLabel,
    signedPercent,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Fund, WindowAverage } from '@/types/fiis';

/** Abaixo disso a regra automática usa P/VP 1,0 no lugar da mediana (alert.md). */
export const MIN_DAYS_FOR_MEDIAN = 60;

function Card({
    label,
    value,
    valueClass,
    detail,
}: {
    label: string;
    value: string;
    valueClass?: string;
    detail: React.ReactNode;
}) {
    return (
        <div className="min-w-0 surface p-4">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className={cn('mt-1 num text-2xl font-semibold', valueClass)}>
                {value}
            </dd>
            <dd className="mt-1 text-xs text-muted-foreground">{detail}</dd>
        </div>
    );
}

function PriceToBookCard({ fund }: { fund: Fund }) {
    if (fund.priceToBook === null) {
        return (
            <Card
                label="P/VP"
                value="Indisponível"
                detail="Sem informe da CVM para calcular."
            />
        );
    }

    const short = fund.priceToBookDays < MIN_DAYS_FOR_MEDIAN;
    const reference = short
        ? 1
        : fund.medianPriceToBook === null
          ? null
          : Number(fund.medianPriceToBook);
    const current = Number(fund.priceToBook);
    const verdict =
        reference === null || current === reference
            ? null
            : current < reference
              ? 'Mais barato'
              : 'Mais caro';

    return (
        <Card
            label="P/VP"
            value={ratio(fund.priceToBook)}
            detail={
                <>
                    {short
                        ? 'Comparando com P/VP 1,0 até juntar histórico'
                        : `Mediana 12m: ${ratio(fund.medianPriceToBook ?? '0')}`}
                    {verdict && (
                        <>
                            {' · '}
                            <span
                                className={cn(
                                    'font-semibold',
                                    verdict === 'Mais barato' && 'text-signal',
                                )}
                            >
                                {verdict}
                            </span>
                        </>
                    )}
                </>
            }
        />
    );
}

function AverageCard({
    label,
    price,
    priceNote,
    average,
}: {
    label: string;
    price: string | null;
    /** Só para quem não é favorito: o preço é o fechamento, com a data. */
    priceNote: string | null;
    average: WindowAverage | undefined;
}) {
    if (price === null) {
        return (
            <Card
                label={label}
                value="Indisponível"
                detail={
                    priceNote === null
                        ? 'Sem cotação por enquanto.'
                        : 'Sem fechamento recente.'
                }
            />
        );
    }

    if (average === undefined || average.value === null) {
        return (
            <Card
                label={label}
                value="—"
                valueClass="text-muted-foreground"
                detail={
                    average === undefined
                        ? 'Sem histórico guardado.'
                        : `Juntando histórico: ${average.monthsStored} de ${average.months} meses`
                }
            />
        );
    }

    const diff = (Number(price) / Number(average.value) - 1) * 100;

    return (
        <Card
            label={label}
            value={signedPercent(String(diff))}
            valueClass={diff < 0 ? 'text-signal' : undefined}
            detail={`Média ${brl(average.value)}${priceNote === null ? '' : ` · preço do ${priceNote}`}`}
        />
    );
}

export function DecisionSummary({ fund }: { fund: Fund }) {
    const lastDividend = fund.dividends?.at(-1);
    const priceNote = fund.favorite
        ? null
        : fund.closeDay === null
          ? 'fechamento da B3'
          : `fechamento de ${dayMonth(fund.closeDay)} (B3)`;

    return (
        <section aria-labelledby="resumo" className="@container">
            <h2 id="resumo" className="sr-only">
                Resumo
            </h2>
            <dl className="grid grid-cols-2 gap-3 @2xl:grid-cols-4">
                <PriceToBookCard fund={fund} />
                <AverageCard
                    label="Preço × média 6m"
                    price={fund.price}
                    priceNote={priceNote}
                    average={fund.average6m}
                />
                <AverageCard
                    label="Preço × média 12m"
                    price={fund.price}
                    priceNote={priceNote}
                    average={fund.average12m}
                />
                {lastDividend === undefined ? (
                    <Card
                        label="Último rendimento"
                        value="—"
                        valueClass="text-muted-foreground"
                        detail="Sem informes da CVM para estimar."
                    />
                ) : (
                    <Card
                        label="Último rendimento"
                        value={brl(lastDividend.perShare)}
                        detail={`Por cota · ${shortMonthLabel(lastDividend.month)} · estimado`}
                    />
                )}
            </dl>
        </section>
    );
}

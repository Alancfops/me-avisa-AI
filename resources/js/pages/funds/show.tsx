import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { RuleBadges, ruleLabel } from '@/components/rule-badges';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { brl, dateTime, signedPercent } from '@/lib/format';

type Fund = {
    ticker: string;
    name: string | null;
    price: string | null;
    changePercent: string | null;
    quotedAt: string | null;
    average30d: string | null;
    matchedRules: string[];
};

type Settings = {
    mode: 'automatic' | 'target' | 'range';
    target: string | null;
    rangeMin: string | null;
    rangeMax: string | null;
    sharpDrop: boolean;
};

type RuleStatus = {
    rule: string;
    matched: boolean;
    reason: string | null;
    unavailableReason: string | null;
    note: string | null;
};

const modes: { value: Settings['mode']; label: string }[] = [
    { value: 'target', label: 'Preço alvo' },
    { value: 'range', label: 'Faixa de compra' },
    { value: 'automatic', label: 'Automático' },
];

function toField(value: string | null): string {
    return value === null ? '' : value.replace('.', ',');
}

function PriceField({
    id,
    label,
    value,
    error,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="space-y-1">
            <Label htmlFor={id}>{label}</Label>
            <div className="flex items-center gap-2">
                <span aria-hidden="true">R$</span>
                <Input
                    id={id}
                    inputMode="decimal"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    aria-invalid={error !== undefined}
                    aria-describedby={error ? `${id}-erro` : undefined}
                />
            </div>
            {error && (
                <p id={`${id}-erro`} className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}

function WhenToAlert({
    ticker,
    settings,
}: {
    ticker: string;
    settings: Settings;
}) {
    const form = useForm({
        mode: settings.mode,
        target: toField(settings.target),
        rangeMin: toField(settings.rangeMin),
        rangeMax: toField(settings.rangeMax),
        sharpDrop: settings.sharpDrop,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put(`/fundos/${ticker}/regras`, { preserveScroll: true });
    }

    return (
        <section
            aria-labelledby="quando-avisar"
            className="space-y-4 rounded-xl border p-5"
        >
            <h2 id="quando-avisar" className="text-lg font-semibold">
                Quando me avisar
            </h2>

            <form onSubmit={submit} className="space-y-4">
                <fieldset className="space-y-2">
                    <legend className="font-medium">Referência</legend>
                    {modes.map((mode) => (
                        <div
                            key={mode.value}
                            className="flex items-center gap-2"
                        >
                            <input
                                type="radio"
                                id={`modo-${mode.value}`}
                                name="mode"
                                value={mode.value}
                                checked={form.data.mode === mode.value}
                                onChange={() =>
                                    form.setData('mode', mode.value)
                                }
                            />
                            <Label htmlFor={`modo-${mode.value}`}>
                                {mode.label}
                            </Label>
                        </div>
                    ))}
                    <InputError message={form.errors.mode} />
                </fieldset>

                {form.data.mode === 'target' && (
                    <PriceField
                        id="target"
                        label="Preço alvo"
                        value={form.data.target}
                        error={form.errors.target}
                        onChange={(value) => form.setData('target', value)}
                    />
                )}

                {form.data.mode === 'range' && (
                    <>
                        <PriceField
                            id="rangeMin"
                            label="Mínimo da faixa"
                            value={form.data.rangeMin}
                            error={form.errors.rangeMin}
                            onChange={(value) =>
                                form.setData('rangeMin', value)
                            }
                        />
                        <PriceField
                            id="rangeMax"
                            label="Máximo da faixa"
                            value={form.data.rangeMax}
                            error={form.errors.rangeMax}
                            onChange={(value) =>
                                form.setData('rangeMax', value)
                            }
                        />
                    </>
                )}

                {form.data.mode === 'automatic' && (
                    <p className="text-sm text-muted-foreground">
                        Avisa quando o preço fica abaixo de 95% da média de 30
                        pregões e o P/VP está abaixo da mediana de 12 meses do
                        próprio fundo.
                    </p>
                )}

                <div className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        id="sharpDrop"
                        checked={form.data.sharpDrop}
                        onChange={(event) =>
                            form.setData('sharpDrop', event.target.checked)
                        }
                    />
                    <Label htmlFor="sharpDrop">
                        Queda forte no dia (mais de 3%)
                    </Label>
                </div>

                <p className="rounded-md bg-muted p-3 text-sm">
                    No máximo 1 aviso por regra a cada 24 h, salvo se o preço
                    cair mais 2% desde o último aviso.
                </p>

                <Button
                    type="submit"
                    className="w-full"
                    disabled={form.processing}
                >
                    Salvar
                </Button>
                <p role="status" className="text-sm">
                    {form.recentlySuccessful && 'Configuração salva.'}
                </p>
            </form>
        </section>
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
    return (
        <>
            <Head title={fund.ticker} />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-8">
                <Link href="/" className="underline">
                    ← Voltar para o painel
                </Link>

                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold">{fund.ticker}</h1>
                    {fund.name && (
                        <p className="text-muted-foreground">{fund.name}</p>
                    )}
                    <RuleBadges
                        rules={fund.matchedRules}
                        hasQuote={fund.price !== null}
                    />

                    {fund.price === null || fund.quotedAt === null ? (
                        <p>Cotação indisponível por enquanto.</p>
                    ) : (
                        <>
                            <p className="text-3xl font-semibold tabular-nums">
                                {brl(fund.price)}
                            </p>
                            <p className="tabular-nums">
                                {fund.changePercent === null
                                    ? 'Variação indisponível'
                                    : `${signedPercent(fund.changePercent)} hoje`}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Cotação de {dateTime(fund.quotedAt)}
                            </p>
                        </>
                    )}
                </header>

                <WhenToAlert ticker={fund.ticker} settings={settings} />

                <section aria-labelledby="dados" className="space-y-2">
                    <h2 id="dados" className="text-lg font-semibold">
                        Dados do fundo
                    </h2>
                    <p>
                        Média de 30 pregões:{' '}
                        {fund.average30d === null
                            ? 'indisponível'
                            : brl(fund.average30d)}
                    </p>
                </section>

                {rules.length > 0 && (
                    <section aria-labelledby="regras" className="space-y-2">
                        <h2 id="regras" className="text-lg font-semibold">
                            Situação das regras
                        </h2>
                        <ul className="space-y-2">
                            {rules.map((status) => (
                                <li key={status.rule}>
                                    <strong>{ruleLabel(status.rule)}:</strong>{' '}
                                    {status.unavailableReason ??
                                        status.reason ??
                                        'não bateu agora.'}
                                    {status.note && (
                                        <span className="block text-sm text-muted-foreground">
                                            {status.note}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <Button
                    variant="ghost"
                    className="text-destructive"
                    onClick={() => {
                        if (
                            window.confirm(
                                `Remover ${fund.ticker} dos favoritos?`,
                            )
                        ) {
                            router.delete(`/favoritos/${fund.ticker}`);
                        }
                    }}
                >
                    Remover dos favoritos
                </Button>
            </div>
        </>
    );
}

FundShow.layout = {
    breadcrumbs: [{ title: 'Painel', href: '/' }],
};

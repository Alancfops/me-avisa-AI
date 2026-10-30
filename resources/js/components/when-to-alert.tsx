import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { SwitchField } from '@/components/switch-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { consumeWhenToAlertFocus } from '@/lib/focus-intent';
import type { AlertMode, Settings } from '@/types/fiis';

const FIELD_ORDER = ['target', 'rangeMin', 'rangeMax'] as const;

const SAVED = 'Configuração salva.';
const FAILED = 'Não conseguimos salvar agora. Tente de novo em instantes.';

function toField(value: string | null): string {
    if (value === null) {
        return '';
    }

    const [whole, decimals = ''] = value.split('.');

    return `${whole},${decimals.padEnd(2, '0')}`;
}

function formDataFrom(settings: Settings) {
    return {
        mode: settings.mode,
        target: toField(settings.target),
        rangeMin: toField(settings.rangeMin),
        rangeMax: toField(settings.rangeMax),
        sharpDrop: settings.sharpDrop,
    };
}

/** Aceita "R$ 9,50"; a vírgula quem normaliza é o backend. */
function cleanPrice(value: string): string {
    return value.replace(/R\$/i, '').trim();
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
        <div className="space-y-1.5">
            <Label htmlFor={id}>{label}</Label>
            <div className="flex items-center gap-2">
                <span aria-hidden="true" className="num text-muted-foreground">
                    R$
                </span>
                <Input
                    id={id}
                    inputMode="decimal"
                    autoComplete="off"
                    placeholder="9,50"
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

export function WhenToAlert({
    ticker,
    settings,
    shortHistory,
}: {
    ticker: string;
    settings: Settings;
    shortHistory: boolean;
}) {
    const form = useForm(formDataFrom(settings));
    const [feedback, setFeedback] = useState('');
    const [failure, setFailure] = useState('');
    const heading = useRef<HTMLHeadingElement>(null);

    useEffect(() => {
        if (consumeWhenToAlertFocus()) {
            heading.current?.focus();
        }
    }, []);

    const modes: { value: AlertMode; label: string; description: string }[] = [
        {
            value: 'target',
            label: 'Preço alvo',
            description: 'Avisa quando o preço ficar igual ou abaixo do alvo.',
        },
        {
            value: 'range',
            label: 'Faixa de compra',
            description:
                'Avisa quando o preço estiver dentro da faixa, limites inclusive.',
        },
        {
            value: 'automatic',
            label: 'Automático',
            description: shortHistory
                ? 'Avisa quando o preço ficar abaixo de 95% da média de 30 pregões e o P/VP abaixo de 1,0, porque o fundo ainda tem menos de 60 pregões de histórico.'
                : 'Avisa quando o preço ficar abaixo de 95% da média de 30 pregões e o P/VP abaixo da mediana de 12 meses do próprio fundo.',
        },
    ];

    function edit<K extends keyof ReturnType<typeof formDataFrom>>(
        field: K,
        value: ReturnType<typeof formDataFrom>[K],
    ) {
        form.setData((data) => ({ ...data, [field]: value }));
        setFeedback('');
        setFailure('');
    }

    function unavailable() {
        setFailure(FAILED);
        toast.error(FAILED);

        return false as const;
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        setFeedback('');
        setFailure('');

        // Campo escondido não vai junto: lixo no alvo não pode travar quem
        // acabou de escolher o modo automático.
        form.transform((data) => ({
            mode: data.mode,
            sharpDrop: data.sharpDrop,
            target: data.mode === 'target' ? cleanPrice(data.target) : null,
            rangeMin: data.mode === 'range' ? cleanPrice(data.rangeMin) : null,
            rangeMax: data.mode === 'range' ? cleanPrice(data.rangeMax) : null,
        }));

        form.put(`/fundos/${ticker}/regras`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                form.setData(formDataFrom(page.props.settings as Settings));
                setFeedback(SAVED);
                toast.success(SAVED);
            },
            onError: (errors) => {
                const first = FIELD_ORDER.find((field) => errors[field]);

                if (first) {
                    document.getElementById(first)?.focus();
                }
            },
            onHttpException: unavailable,
            onNetworkError: unavailable,
        });
    }

    return (
        <section aria-labelledby="quando-avisar" className="surface p-5">
            <h2
                id="quando-avisar"
                ref={heading}
                tabIndex={-1}
                className="text-lg font-semibold outline-none"
            >
                Quando me avisar
            </h2>

            <form onSubmit={submit} noValidate className="mt-4 space-y-5">
                <fieldset className="space-y-2">
                    <legend className="mb-2 text-sm font-medium">
                        Referência de preço
                    </legend>
                    {modes.map((mode) => (
                        <label
                            key={mode.value}
                            htmlFor={`modo-${mode.value}`}
                            className="flex cursor-pointer items-start gap-3 rounded-lg border border-input p-3 has-checked:border-primary has-checked:bg-primary-soft"
                        >
                            <input
                                id={`modo-${mode.value}`}
                                type="radio"
                                name="mode"
                                value={mode.value}
                                checked={form.data.mode === mode.value}
                                onChange={() => edit('mode', mode.value)}
                                className="mt-0.5 size-4 shrink-0 accent-primary"
                            />
                            <span className="min-w-0">
                                <span className="block text-sm font-medium">
                                    {mode.label}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {mode.description}
                                </span>
                            </span>
                        </label>
                    ))}
                    <InputError message={form.errors.mode} />
                </fieldset>

                {form.data.mode === 'target' && (
                    <PriceField
                        id="target"
                        label="Preço alvo"
                        value={form.data.target}
                        error={form.errors.target}
                        onChange={(value) => edit('target', value)}
                    />
                )}

                {form.data.mode === 'range' && (
                    <div className="space-y-3">
                        <PriceField
                            id="rangeMin"
                            label="Mínimo da faixa"
                            value={form.data.rangeMin}
                            error={form.errors.rangeMin}
                            onChange={(value) => edit('rangeMin', value)}
                        />
                        <PriceField
                            id="rangeMax"
                            label="Máximo da faixa"
                            value={form.data.rangeMax}
                            error={form.errors.rangeMax}
                            onChange={(value) => edit('rangeMax', value)}
                        />
                    </div>
                )}

                <SwitchField
                    id="sharpDrop"
                    label="Queda forte no dia"
                    description="Avisa se o fundo cair mais de 3% no pregão."
                    checked={form.data.sharpDrop}
                    onChange={(checked) => edit('sharpDrop', checked)}
                />
                <InputError message={form.errors.sharpDrop} />

                <p className="rounded-lg bg-muted p-3 text-xs text-muted-foreground">
                    No máximo 1 aviso por regra a cada 24 h, salvo se o preço
                    cair mais 2% desde o último aviso.
                </p>

                <Button
                    type="submit"
                    className="w-full"
                    disabled={form.processing}
                >
                    {form.processing ? 'Salvando…' : 'Salvar'}
                </Button>

                <p role="status" className="text-sm empty:hidden">
                    {feedback}
                </p>
                {failure && (
                    <p role="alert" className="text-sm text-destructive">
                        {failure}
                    </p>
                )}
            </form>
        </section>
    );
}

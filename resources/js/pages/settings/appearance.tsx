import { Head, router, useForm } from '@inertiajs/react';
import { CircleCheck, Monitor, Moon, Sun } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Appearance as Theme } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

const themes: { value: Theme; label: string; icon: LucideIcon }[] = [
    { value: 'system', label: 'Sistema', icon: Monitor },
    { value: 'light', label: 'Claro', icon: Sun },
    { value: 'dark', label: 'Escuro', icon: Moon },
];

type KeySource = 'env' | 'app' | null;

function BrapiKeyForm({ onDone }: { onDone?: () => void }) {
    const form = useForm({ token: '' });

    return (
        <form
            className="space-y-2"
            onSubmit={(event) => {
                event.preventDefault();
                form.post('/settings/brapi', {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        toast.success('Chave da brapi salva.');
                        onDone?.();
                    },
                });
            }}
        >
            <Label htmlFor="brapi-chave">Chave (token) da brapi</Label>
            <Input
                id="brapi-chave"
                value={form.data.token}
                onChange={(event) => form.setData('token', event.target.value)}
                autoComplete="off"
                spellCheck={false}
                aria-invalid={form.errors.token !== undefined}
                aria-describedby={
                    form.errors.token ? 'brapi-chave-erro' : undefined
                }
                className="num"
            />
            {form.errors.token && (
                <p
                    id="brapi-chave-erro"
                    role="alert"
                    className="text-sm text-destructive"
                >
                    {form.errors.token}
                </p>
            )}
            <Button
                type="submit"
                disabled={form.processing || form.data.token.trim() === ''}
            >
                {form.processing ? 'Conferindo…' : 'Salvar chave'}
            </Button>
        </form>
    );
}

function BrapiKey({ source }: { source: KeySource }) {
    const [changing, setChanging] = useState(false);

    return (
        <section
            id="brapi"
            aria-labelledby="brapi-titulo"
            className="space-y-4 surface p-5"
        >
            <div className="space-y-1">
                <h2 id="brapi-titulo" className="text-lg font-semibold">
                    Chave da brapi
                </h2>
                <p className="text-sm text-muted-foreground">
                    A brapi fornece as cotações dos seus favoritos durante o
                    pregão. A conta é gratuita.
                </p>
            </div>

            {source === 'env' && (
                <p className="flex items-start gap-2 text-sm">
                    <CircleCheck
                        className="mt-0.5 size-4 shrink-0 text-signal"
                        aria-hidden="true"
                    />
                    A chave vem do arquivo <code>.env</code> (linha{' '}
                    <code>BRAPI_TOKEN</code>). Para trocar, edite o arquivo e
                    rode <code>make start</code>.
                </p>
            )}

            {source === 'app' && !changing && (
                <div className="space-y-3">
                    <p className="flex items-start gap-2 text-sm">
                        <CircleCheck
                            className="mt-0.5 size-4 shrink-0 text-signal"
                            aria-hidden="true"
                        />
                        Chave salva e conferida com a brapi.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setChanging(true)}
                        >
                            Trocar chave
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            className="text-destructive hover:text-destructive"
                            onClick={() =>
                                router.delete('/settings/brapi', {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Remover chave
                        </Button>
                    </div>
                </div>
            )}

            {(source === null || changing) && (
                <>
                    <ol className="list-decimal space-y-2 pl-5 text-sm">
                        <li>
                            Crie uma conta gratuita em{' '}
                            <a
                                href="https://brapi.dev/dashboard"
                                target="_blank"
                                rel="noreferrer"
                                className="text-primary underline"
                            >
                                brapi.dev/dashboard
                            </a>
                            .
                        </li>
                        <li>
                            No painel, copie o seu token. O plano grátis dá 15
                            mil requisições por mês, e cada favorito usa cerca
                            de 350.
                        </li>
                        <li>Cole abaixo e salve. O app confere com a brapi.</li>
                    </ol>
                    <BrapiKeyForm onDone={() => setChanging(false)} />
                </>
            )}

            <p className="text-sm text-muted-foreground">
                A chave fica guardada só neste computador, criptografada. Não a
                compartilhe.
            </p>
        </section>
    );
}

export default function Appearance({
    brapi,
}: {
    brapi: { source: KeySource };
}) {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <>
            <Head title="Configurações" />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                <PageHeader title="Configurações" />

                <BrapiKey source={brapi.source} />

                <section aria-labelledby="tema" className="surface p-5">
                    <h2 id="tema" className="text-lg font-semibold">
                        Tema
                    </h2>
                    <fieldset className="mt-4">
                        <legend className="text-sm text-muted-foreground">
                            Sistema segue a configuração do seu aparelho.
                        </legend>
                        <div className="mt-3 grid gap-2 sm:grid-cols-3">
                            {themes.map(({ value, label, icon: Icon }) => (
                                <label
                                    key={value}
                                    htmlFor={`tema-${value}`}
                                    className="flex cursor-pointer items-center gap-3 rounded-lg border border-input p-3 text-sm font-medium has-checked:border-primary has-checked:bg-primary-soft"
                                >
                                    <input
                                        id={`tema-${value}`}
                                        type="radio"
                                        name="tema"
                                        value={value}
                                        checked={appearance === value}
                                        onChange={() => updateAppearance(value)}
                                        className="size-4 shrink-0 accent-primary"
                                    />
                                    <Icon
                                        className="size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                </section>
            </div>
        </>
    );
}

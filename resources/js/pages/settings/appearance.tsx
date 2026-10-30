import { Head } from '@inertiajs/react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import type { Appearance as Theme } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

const themes: { value: Theme; label: string; icon: LucideIcon }[] = [
    { value: 'system', label: 'Sistema', icon: Monitor },
    { value: 'light', label: 'Claro', icon: Sun },
    { value: 'dark', label: 'Escuro', icon: Moon },
];

export default function Appearance() {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <>
            <Head title="Configurações" />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                <PageHeader title="Configurações" />

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

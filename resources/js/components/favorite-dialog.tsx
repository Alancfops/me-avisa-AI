import { Link, router } from '@inertiajs/react';
import { Check, Search as SearchIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ModalContent } from '@/components/modal';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    cancelWhenToAlertFocus,
    requestWhenToAlertFocus,
} from '@/lib/focus-intent';
import type { Search } from '@/types/fiis';

const DEBOUNCE_MS = 300;

function runSearch(term: string, onFail: () => void = () => {}): void {
    router.get('/', term === '' ? {} : { q: term }, {
        only: ['search'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onHttpException: () => {
            onFail();

            return false;
        },
        onNetworkError: () => {
            onFail();

            return false;
        },
    });
}

function searchStatus(search: Search, term: string, failed: boolean): string {
    if (failed || term === '') {
        return '';
    }

    if (term !== search.query.trim()) {
        return 'Buscando…';
    }

    if (search.results.length === 0) {
        return 'Nenhum FII encontrado. Confira o código na B3.';
    }

    return search.results.length === 1
        ? '1 fundo encontrado.'
        : `${search.results.length} fundos encontrados.`;
}

function FavoriteSearch({ search }: { search: Search }) {
    const [value, setValue] = useState(search.query);
    const [failed, setFailed] = useState(false);
    const [saving, setSaving] = useState<string | null>(null);
    const [saveError, setSaveError] = useState<string | null>(null);
    const timer = useRef<number | undefined>(undefined);
    const term = value.trim();
    const status = searchStatus(search, term, failed);
    const searching = status === 'Buscando…';

    useEffect(() => () => window.clearTimeout(timer.current), []);

    function searchNow(next: string) {
        window.clearTimeout(timer.current);
        setFailed(false);
        runSearch(next, () => setFailed(true));
    }

    function change(next: string) {
        setValue(next);
        setSaveError(null);
        window.clearTimeout(timer.current);
        setFailed(false);
        timer.current = window.setTimeout(
            () => searchNow(next.trim()),
            DEBOUNCE_MS,
        );
    }

    function favorite(ticker: string) {
        setSaving(ticker);
        setSaveError(null);
        requestWhenToAlertFocus();

        const fail = (message: string) => {
            cancelWhenToAlertFocus();
            setSaving(null);
            setSaveError(message);
        };
        const unavailable = () => {
            fail(
                'Não conseguimos favoritar agora. Tente de novo em instantes.',
            );

            return false;
        };

        router.post(
            '/favoritos',
            { ticker },
            {
                onError: (errors) =>
                    fail(errors.ticker ?? 'Não conseguimos favoritar agora.'),
                onHttpException: unavailable,
                onNetworkError: unavailable,
            },
        );
    }

    if (search.catalogEmpty) {
        return (
            <p className="text-sm text-muted-foreground">
                A lista de fundos ainda não foi carregada. Ela atualiza todo dia
                às 7h; se demorar, veja o que houve em{' '}
                <Link href="/saude" className="text-primary underline">
                    Saúde
                </Link>
                .
            </p>
        );
    }

    const showResults = term !== '' && search.results.length > 0 && !failed;

    return (
        <>
            <form
                role="search"
                className="space-y-1.5"
                onSubmit={(event) => {
                    event.preventDefault();
                    searchNow(term);
                }}
            >
                <Label htmlFor="favoritar-busca">Código ou nome do fundo</Label>
                <div className="relative">
                    <SearchIcon
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        id="favoritar-busca"
                        name="q"
                        value={value}
                        onChange={(event) => change(event.target.value)}
                        autoComplete="off"
                        spellCheck={false}
                        className="pl-9"
                    />
                </div>
            </form>

            <p role="status" className="min-h-5 text-sm text-muted-foreground">
                {status}
            </p>

            {failed && (
                <p role="alert" className="text-sm text-destructive">
                    Não conseguimos buscar agora. Tente de novo em instantes.
                </p>
            )}

            {saveError && (
                <p role="alert" className="text-sm text-destructive">
                    {saveError}
                </p>
            )}

            {showResults && (
                <ul
                    aria-busy={searching}
                    className="divide-y rounded-lg border"
                >
                    {search.results.map((fund) => (
                        <li
                            key={fund.ticker}
                            className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 p-3"
                        >
                            <span className="min-w-0">
                                <span className="block font-display font-semibold">
                                    {fund.ticker}
                                </span>
                                <span className="block text-sm wrap-anywhere text-muted-foreground">
                                    {fund.name}
                                </span>
                            </span>
                            {fund.favorite ? (
                                <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <Check
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Já está nos favoritos
                                </span>
                            ) : (
                                <Button
                                    size="sm"
                                    disabled={saving !== null}
                                    onClick={() => favorite(fund.ticker)}
                                >
                                    {saving === fund.ticker
                                        ? `Favoritando ${fund.ticker}…`
                                        : `Favoritar ${fund.ticker}`}
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}

export function FavoriteDialog({
    open,
    onOpenChange,
    search,
    returnFocusTo,
}: {
    returnFocusTo: HTMLElement | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    search: Search;
}) {
    function change(next: boolean) {
        onOpenChange(next);

        if (!next && search.query !== '') {
            runSearch('');
        }
    }

    return (
        <Dialog open={open} onOpenChange={change}>
            <ModalContent
                onCloseAutoFocus={(event) => {
                    // Sem DialogTrigger, o Radix não sabe para onde devolver o foco.
                    event.preventDefault();

                    if (returnFocusTo?.isConnected) {
                        returnFocusTo.focus();
                    } else if (document.activeElement === document.body) {
                        document.getElementById('conteudo')?.focus();
                    }
                }}
                onOpenAutoFocus={(event) => {
                    const field = document.getElementById('favoritar-busca');

                    if (field) {
                        event.preventDefault();
                        field.focus();
                    }
                }}
            >
                <div className="space-y-1 pr-8">
                    <DialogTitle className="font-display text-xl">
                        Favoritar FII
                    </DialogTitle>
                    <DialogDescription>
                        Busque pelo código, como MXRF11, ou pelo nome. Depois
                        você escolhe quando ser avisado.
                    </DialogDescription>
                </div>
                <FavoriteSearch search={search} />
            </ModalContent>
        </Dialog>
    );
}

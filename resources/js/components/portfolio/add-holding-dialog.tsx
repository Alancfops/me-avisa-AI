import { router } from '@inertiajs/react';
import { Search as SearchIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ModalContent } from '@/components/modal';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { parseShares, saveHolding } from '@/lib/portfolio';
import type { PortfolioSearch } from '@/types/fiis';

const DEBOUNCE_MS = 300;

function runSearch(term: string): void {
    router.get('/carteira', term === '' ? {} : { q: term }, {
        only: ['search'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

/** Busca no catálogo, escolhe o fundo e informa as cotas. */
export function AddHoldingDialog({
    open,
    onOpenChange,
    search,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    search: PortfolioSearch;
}) {
    const [value, setValue] = useState(search.query);
    const [chosen, setChosen] = useState<string | null>(null);
    const [sharesText, setSharesText] = useState('');
    const [saving, setSaving] = useState(false);
    const timer = useRef<number | undefined>(undefined);
    const term = value.trim();
    const shares = parseShares(sharesText);
    const searching = term !== '' && term !== search.query.trim();

    useEffect(() => () => window.clearTimeout(timer.current), []);

    function change(next: string) {
        setValue(next);
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(
            () => runSearch(next.trim()),
            DEBOUNCE_MS,
        );
    }

    function close(next: boolean) {
        onOpenChange(next);

        if (!next) {
            setValue('');
            setChosen(null);
            setSharesText('');

            if (search.query !== '') {
                runSearch('');
            }
        }
    }

    function save() {
        if (chosen === null || shares === null) {
            return;
        }

        setSaving(true);
        saveHolding(chosen, shares, {
            onDone: () => close(false),
            onFinish: () => setSaving(false),
        });
    }

    const status =
        term === ''
            ? ''
            : searching
              ? 'Buscando…'
              : search.results.length === 0
                ? 'Nenhum FII encontrado. Confira o código na B3.'
                : `${search.results.length} ${search.results.length === 1 ? 'fundo encontrado' : 'fundos encontrados'}.`;

    return (
        <Dialog open={open} onOpenChange={close}>
            <ModalContent>
                <div className="space-y-1 pr-8">
                    <DialogTitle className="font-display text-xl">
                        Adicionar FII à carteira
                    </DialogTitle>
                    <DialogDescription>
                        Busque pelo código, como MXRF11, ou pelo nome, e informe
                        quantas cotas você tem.
                    </DialogDescription>
                </div>

                {search.catalogEmpty ? (
                    <p className="text-sm text-muted-foreground">
                        A lista de fundos ainda não foi carregada. Ela atualiza
                        todo dia às 7h.
                    </p>
                ) : chosen === null ? (
                    <>
                        <div role="search" className="space-y-1.5">
                            <Label htmlFor="carteira-busca">
                                Código ou nome do fundo
                            </Label>
                            <div className="relative">
                                <SearchIcon
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    id="carteira-busca"
                                    value={value}
                                    onChange={(event) =>
                                        change(event.target.value)
                                    }
                                    autoComplete="off"
                                    spellCheck={false}
                                    className="pl-9"
                                />
                            </div>
                        </div>
                        <p
                            role="status"
                            className="min-h-5 text-sm text-muted-foreground"
                        >
                            {status}
                        </p>
                        {term !== '' && search.results.length > 0 && (
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
                                        <Button
                                            size="sm"
                                            variant={
                                                fund.inPortfolio
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                            onClick={() =>
                                                setChosen(fund.ticker)
                                            }
                                        >
                                            {fund.inPortfolio
                                                ? `Atualizar ${fund.ticker}`
                                                : `Escolher ${fund.ticker}`}
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </>
                ) : (
                    <form
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            save();
                        }}
                    >
                        <div className="space-y-1.5">
                            <Label htmlFor="carteira-cotas">
                                Quantas cotas de {chosen} você tem
                            </Label>
                            <Input
                                id="carteira-cotas"
                                inputMode="numeric"
                                autoComplete="off"
                                autoFocus
                                value={sharesText}
                                onChange={(event) =>
                                    setSharesText(event.target.value)
                                }
                                aria-invalid={
                                    sharesText !== '' && shares === null
                                }
                                className="max-w-32 num"
                            />
                            {sharesText !== '' && shares === null && (
                                <p className="text-sm text-destructive">
                                    Informe um número inteiro de cotas, como
                                    100.
                                </p>
                            )}
                        </div>
                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setChosen(null)}
                            >
                                Voltar
                            </Button>
                            <Button
                                type="submit"
                                disabled={shares === null || saving}
                            >
                                {saving ? 'Salvando…' : 'Salvar na carteira'}
                            </Button>
                        </div>
                    </form>
                )}
            </ModalContent>
        </Dialog>
    );
}

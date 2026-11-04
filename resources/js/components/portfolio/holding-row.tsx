import { Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { ModalContent } from '@/components/modal';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { brl } from '@/lib/format';
import { parseShares, priceNote, saveHolding } from '@/lib/portfolio';
import type { PortfolioRow } from '@/types/fiis';

function Missing({ children }: { children: string }) {
    return <span className="text-muted-foreground">{children}</span>;
}

export function HoldingRow({ row }: { row: PortfolioRow }) {
    const [text, setText] = useState(String(row.shares));
    const [saving, setSaving] = useState(false);
    const shares = parseShares(text);
    const changed = shares !== null && shares !== row.shares;
    const fieldId = `cotas-${row.ticker}`;

    return (
        <tr className="border-t align-top">
            <th scope="row" className="p-3 text-left font-normal">
                <Link
                    href={`/fundos/${row.ticker}`}
                    className="font-display font-semibold text-primary underline-offset-4 hover:underline"
                >
                    {row.ticker}
                </Link>
                {row.name && (
                    <span className="block text-sm wrap-anywhere text-muted-foreground">
                        {row.name}
                    </span>
                )}
            </th>
            <td className="p-3">
                <form
                    className="flex items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (changed) {
                            setSaving(true);
                            saveHolding(row.ticker, shares, {
                                onFinish: () => setSaving(false),
                            });
                        }
                    }}
                >
                    <label htmlFor={fieldId} className="sr-only">
                        Cotas de {row.ticker}
                    </label>
                    <Input
                        id={fieldId}
                        inputMode="numeric"
                        autoComplete="off"
                        value={text}
                        onChange={(event) => setText(event.target.value)}
                        aria-invalid={shares === null}
                        className="w-24 num"
                    />
                    {changed && (
                        <Button type="submit" size="sm" disabled={saving}>
                            Salvar
                        </Button>
                    )}
                </form>
                {shares === null && (
                    <p className="mt-1 text-sm text-destructive">
                        Informe um número inteiro, como 100.
                    </p>
                )}
            </td>
            <td className="p-3 num">
                {row.price === null ? (
                    <Missing>sem preço</Missing>
                ) : (
                    <>
                        {brl(row.price)}
                        <span className="block text-xs text-muted-foreground">
                            {priceNote(row.quotedAt, row.closeDay)}
                        </span>
                    </>
                )}
            </td>
            <td className="p-3 num">
                {row.positionValue === null ? (
                    <Missing>sem preço</Missing>
                ) : (
                    brl(row.positionValue)
                )}
            </td>
            <td className="p-3 num">
                {row.lastIncome === null || row.averageIncome === null ? (
                    <Missing>sem rendimento informado</Missing>
                ) : (
                    <>
                        {brl(row.lastIncome)}
                        <span className="block text-xs text-muted-foreground">
                            média{' '}
                            {row.dividendMonths === 12
                                ? '12m'
                                : `${row.dividendMonths}m`}
                            : {brl(row.averageIncome)}
                        </span>
                    </>
                )}
            </td>
            <td className="p-3 text-right">
                <RemoveHolding ticker={row.ticker} />
            </td>
        </tr>
    );
}

function RemoveHolding({ ticker }: { ticker: string }) {
    const [processing, setProcessing] = useState(false);
    const cancel = useRef<HTMLButtonElement>(null);

    function remove() {
        const unavailable = () => {
            toast.error(
                'Não conseguimos remover agora. Tente de novo em instantes.',
            );

            return false;
        };

        setProcessing(true);
        router.delete(`/carteira/${ticker}`, {
            preserveScroll: true,
            onHttpException: unavailable,
            onNetworkError: unavailable,
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-destructive hover:text-destructive"
                    aria-label={`Remover ${ticker} da carteira`}
                >
                    <Trash2 aria-hidden="true" />
                </Button>
            </DialogTrigger>
            <ModalContent
                role="alertdialog"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    cancel.current?.focus();
                }}
            >
                <div className="space-y-1 pr-8">
                    <DialogTitle className="font-display text-xl">
                        Remover {ticker} da carteira?
                    </DialogTitle>
                    <DialogDescription>
                        As cotas salvas saem da carteira. O fundo continua nos
                        favoritos, se for um.
                    </DialogDescription>
                </div>
                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <DialogClose asChild>
                        <Button ref={cancel} variant="outline">
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        className="text-destructive-foreground"
                        disabled={processing}
                        onClick={remove}
                    >
                        Remover da carteira
                    </Button>
                </div>
            </ModalContent>
        </Dialog>
    );
}

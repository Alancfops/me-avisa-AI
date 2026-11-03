import { router } from '@inertiajs/react';
import { Loader2, Star } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    cancelWhenToAlertFocus,
    requestWhenToAlertFocus,
} from '@/lib/focus-intent';
import { cn } from '@/lib/utils';

const FAILED = 'Não conseguimos favoritar agora. Tente de novo em instantes.';

/**
 * Favorita sem sair da tela (`stay`). Quem já é favorito vê o botão marcado;
 * remover continua na página do fundo.
 */
export function FavoriteButton({
    ticker,
    favorite,
    focusAlert = false,
    compact = false,
    className,
}: {
    ticker: string;
    favorite: boolean;
    /** Na página do fundo, o foco vai para "Quando me avisar" depois de favoritar. */
    focusAlert?: boolean;
    /** Na tabela estreita, só o ícone aparece; o nome acessível continua completo. */
    compact?: boolean;
    className?: string;
}) {
    const [processing, setProcessing] = useState(false);
    const label = processing
        ? 'Favoritando…'
        : favorite
          ? 'Favorito'
          : 'Favoritar';

    function add() {
        if (favorite || processing) {
            return;
        }

        const fail = (message: string) => {
            cancelWhenToAlertFocus();
            toast.error(message);

            return false as const;
        };

        setProcessing(true);

        if (focusAlert) {
            requestWhenToAlertFocus();
        }

        router.post(
            '/favoritos',
            { ticker, stay: true },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () =>
                    toast.success(`${ticker} entrou nos favoritos.`),
                onError: (errors) => {
                    fail(errors.ticker ?? FAILED);
                },
                onHttpException: () => fail(FAILED),
                onNetworkError: () => fail(FAILED),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <Button
            type="button"
            variant={favorite ? 'secondary' : 'outline'}
            size="sm"
            aria-pressed={favorite}
            aria-disabled={favorite || processing}
            aria-label={`${label.replace('…', '')} ${ticker}`}
            onClick={add}
            className={cn(
                'whitespace-nowrap aria-disabled:cursor-default',
                favorite && 'bg-signal-soft text-signal hover:bg-signal-soft',
                className,
            )}
        >
            {processing ? (
                <Loader2
                    className="size-4 motion-safe:animate-spin"
                    aria-hidden="true"
                />
            ) : (
                <Star
                    className={cn('size-4', favorite && 'fill-current')}
                    aria-hidden="true"
                />
            )}
            <span className={cn(compact && 'sr-only sm:not-sr-only')}>
                {label}
            </span>
        </Button>
    );
}

import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { clockTime, dayMonth } from '@/lib/format';

export const ESTIMATE_NOTE =
    'Estimativa pelo DY e pelo VP informados à CVM. O fundo pode pagar outro valor.';

const UNAVAILABLE = 'Não conseguimos salvar agora. Tente de novo em instantes.';

/** Só inteiros positivos: cota não se divide. "1.000" vale 1000. */
export function parseShares(text: string): number | null {
    const clean = text.trim().replace(/\./g, '');

    return /^\d+$/.test(clean) && Number(clean) > 0 ? Number(clean) : null;
}

/** "cotação de 14:30" ou "fechamento de 30/10 (B3)", como na página do fundo. */
export function priceNote(
    quotedAt: string | null,
    closeDay: string | null,
): string {
    if (quotedAt !== null) {
        return `cotação de ${clockTime(quotedAt)}`;
    }

    return closeDay === null
        ? 'fechamento da B3'
        : `fechamento de ${dayMonth(closeDay)} (B3)`;
}

/** Cria ou atualiza as cotas sem sair da tela. */
export function saveHolding(
    ticker: string,
    shares: number,
    { onDone, onFinish }: { onDone?: () => void; onFinish?: () => void } = {},
): void {
    const unavailable = () => {
        toast.error(UNAVAILABLE);

        return false as const;
    };

    router.put(
        `/carteira/${ticker}`,
        { shares, stay: true },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${ticker} salvo na carteira.`);
                onDone?.();
            },
            onError: (errors) =>
                toast.error(errors.shares ?? errors.ticker ?? UNAVAILABLE),
            onHttpException: unavailable,
            onNetworkError: unavailable,
            onFinish,
        },
    );
}

import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { dayMonth } from '@/lib/format';
import { quoteMoment } from '@/lib/market';

export const ESTIMATE_NOTE =
    'Estimativa pelo DY e pelo VP informados à CVM. O fundo pode pagar outro valor.';

const UNAVAILABLE = 'Não conseguimos salvar agora. Tente de novo em instantes.';

/**
 * Só inteiros positivos: cota não se divide. Ponto só vale como milhar ("1.000" é
 * 1000); "10.5" e "1.0" são recusados, para não virarem 105 e 10.
 */
export function parseShares(text: string): number | null {
    const trimmed = text.trim();

    if (!/^(\d+|\d{1,3}(\.\d{3})+)$/.test(trimmed)) {
        return null;
    }

    const shares = Number(trimmed.replace(/\./g, ''));

    return shares > 0 ? shares : null;
}

/** "cotação de 30/10, 14:30" ou "fechamento de 30/10 (B3)", como na página do fundo. */
export function priceNote(
    quotedAt: string | null,
    closeDay: string | null,
): string {
    if (quotedAt !== null) {
        return `cotação de ${quoteMoment(quotedAt)}`;
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

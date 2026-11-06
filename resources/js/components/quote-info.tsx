import { Clock } from 'lucide-react';
import { signedPercent } from '@/lib/format';
import { isToday, quoteFreshness, quoteMoment } from '@/lib/market';
import { cn } from '@/lib/utils';

/** Toda cotação diz de quando é e, se for o caso, por que não é de agora. */
export function QuoteTime({ quotedAt }: { quotedAt: string }) {
    const freshness = quoteFreshness(quotedAt);
    const time = (
        <time dateTime={quotedAt} className="num">
            {quoteMoment(quotedAt)}
        </time>
    );

    if (freshness === 'late') {
        return (
            <p className="flex items-start gap-1.5 text-sm font-medium text-foreground">
                <Clock className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <span>Cotação sem atualizar desde {time}</span>
            </p>
        );
    }

    return (
        <p className="text-sm text-muted-foreground">
            {freshness === 'closed' ? (
                <>Mercado fechado · última cotação de {time}</>
            ) : (
                <>Cotação de {time}</>
            )}
        </p>
    );
}

/** O vermelho fica para a queda forte; o verde, para o preço bom. */
export function DayChange({
    changePercent,
    quotedAt,
    sharpDrop,
}: {
    changePercent: string | null;
    quotedAt: string;
    sharpDrop: boolean;
}) {
    if (changePercent === null) {
        return (
            <p className="text-sm text-muted-foreground">
                Variação indisponível
            </p>
        );
    }

    return (
        <p
            className={cn(
                'num text-sm',
                sharpDrop
                    ? 'font-semibold text-destructive'
                    : 'text-muted-foreground',
            )}
        >
            {signedPercent(changePercent)}{' '}
            {isToday(quotedAt) ? 'hoje' : 'no último pregão'}
        </p>
    );
}

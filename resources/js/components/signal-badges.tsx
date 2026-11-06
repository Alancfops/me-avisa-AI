import { cn } from '@/lib/utils';
import { SHARP_DROP, signalLabel } from '@/lib/rules';

function Badge({
    tone,
    children,
}: {
    tone: 'signal' | 'drop' | 'neutral';
    children: React.ReactNode;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                tone === 'signal' && 'text-signal badge-signal',
                tone === 'drop' && 'bg-drop-soft text-destructive',
                tone === 'neutral' && 'bg-muted text-muted-foreground',
            )}
        >
            <span
                className="size-1.5 shrink-0 rounded-full bg-current"
                aria-hidden="true"
            />
            {children}
        </span>
    );
}

export function RuleBadge({ rule }: { rule: string }) {
    return (
        <Badge tone={rule === SHARP_DROP ? 'drop' : 'signal'}>
            {signalLabel(rule)}
        </Badge>
    );
}

/** Diz qual regra bateu, não um conselho. Queda forte vem primeiro. */
export function SignalBadges({
    rules,
    hasQuote,
}: {
    rules: string[];
    hasQuote: boolean;
}) {
    if (!hasQuote) {
        return null;
    }

    if (rules.length === 0) {
        return <Badge tone="neutral">Observando</Badge>;
    }

    const ordered = [...rules].sort(
        (a, b) => Number(b === SHARP_DROP) - Number(a === SHARP_DROP),
    );

    return (
        <div className="flex flex-wrap gap-1">
            {ordered.map((rule) => (
                <RuleBadge key={rule} rule={rule} />
            ))}
        </div>
    );
}

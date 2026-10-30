import { Badge } from '@/components/ui/badge';

const labels: Record<string, string> = {
    price_target: 'No seu alvo',
    buy_range: 'Na faixa de compra',
    automatic: 'Abaixo da média',
    sharp_drop: 'Queda forte',
};

export function ruleLabel(rule: string): string {
    return labels[rule] ?? rule;
}

export function RuleBadges({
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
        return <Badge variant="secondary">Observando</Badge>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {rules.map((rule) => (
                <Badge
                    key={rule}
                    variant={rule === 'sharp_drop' ? 'destructive' : 'default'}
                >
                    {ruleLabel(rule)}
                </Badge>
            ))}
        </div>
    );
}

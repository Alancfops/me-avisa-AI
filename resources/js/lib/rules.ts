import type { Fund } from '@/types/fiis';

const signalLabels: Record<string, string> = {
    price_target: 'No seu alvo',
    buy_range: 'Na faixa de compra',
    automatic: 'Abaixo da média',
    sharp_drop: 'Queda forte',
};

const ruleNames: Record<string, string> = {
    price_target: 'Preço alvo',
    buy_range: 'Faixa de compra',
    automatic: 'Automático',
    sharp_drop: 'Queda forte',
};

export const SHARP_DROP = 'sharp_drop';

/** O que o badge diz quando a regra bateu. */
export function signalLabel(rule: string): string {
    return signalLabels[rule] ?? rule;
}

/** O nome da regra, tenha ela batido ou não. */
export function ruleName(rule: string): string {
    return ruleNames[rule] ?? rule;
}

export function hasGoodPrice(rules: string[]): boolean {
    return rules.some((rule) => rule !== SHARP_DROP);
}

export function hasSharpDrop(rules: string[]): boolean {
    return rules.includes(SHARP_DROP);
}

function rank(fund: Fund): number {
    if (fund.price === null) {
        return 3;
    }

    if (hasGoodPrice(fund.matchedRules)) {
        return 0;
    }

    return hasSharpDrop(fund.matchedRules) ? 1 : 2;
}

/** Preço bom, queda forte, observando e, por último, quem está sem cotação. */
export function sortForDashboard(funds: Fund[]): Fund[] {
    return [...funds].sort((a, b) => rank(a) - rank(b));
}

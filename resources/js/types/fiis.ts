export type Fund = {
    ticker: string;
    name: string | null;
    price: string | null;
    changePercent: string | null;
    quotedAt: string | null;
    average30d: string | null;
    priceToBook: string | null;
    medianPriceToBook: string | null;
    priceToBookDays: number;
    bookValueMonth: string | null;
    dividendYield12m: string | null;
    matchedRules: string[];
};

export type Search = {
    query: string;
    catalogEmpty: boolean;
    results: { ticker: string; name: string; favorite: boolean }[];
};

export type AlertMode = 'automatic' | 'target' | 'range';

export type Settings = {
    mode: AlertMode;
    target: string | null;
    rangeMin: string | null;
    rangeMax: string | null;
    sharpDrop: boolean;
};

export type RuleStatus = {
    rule: string;
    matched: boolean;
    reason: string | null;
    unavailableReason: string | null;
    note: string | null;
};

export type Alert = {
    ticker: string;
    rule: string;
    price: string;
    reference: string;
    reason: string;
    occurredAt: string;
    quotedAt: string;
};

export type SourceStatus = {
    source: string;
    lastSuccessAt: string | null;
    errors: { message: string; occurredAt: string }[];
};

export type Usage = {
    plan: string;
    limit: number;
    used: number;
    remaining: number;
};

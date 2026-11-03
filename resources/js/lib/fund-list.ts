import type { FundKind, FundListItem } from '@/types/fiis';

export type KindFilter = 'all' | FundKind;
export type BaseFilter = 'all' | 'base10' | 'base100';
export type SortKey = 'priceToBook' | 'dividendYield' | 'ticker' | 'netAssets';

/** Abaixo disso a cota é "Base 10"; a partir daí, "Base 100". */
export const BASE_CUTOFF = 50;

/** Meses de rendimento estimado que o preço teto exige. */
export const CEILING_MONTHS = 12;

export const KIND_LABELS: Record<FundKind, string> = {
    tijolo: 'Tijolo',
    papel: 'Papel',
    fundo_de_fundos: 'Fundo de fundos',
    hibrido: 'Híbrido',
};

export type Filters = {
    query: string;
    kind: KindFilter;
    base: BaseFilter;
};

function normalize(text: string): string {
    return text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();
}

export function filterFunds(
    funds: FundListItem[],
    { query, kind, base }: Filters,
): FundListItem[] {
    const term = normalize(query.trim());

    return funds.filter((fund) => {
        if (kind !== 'all' && fund.kind !== kind) {
            return false;
        }

        if (base !== 'all') {
            if (fund.close === null) {
                return false;
            }

            const isBase10 = Number(fund.close) < BASE_CUTOFF;

            if (isBase10 !== (base === 'base10')) {
                return false;
            }
        }

        return (
            term === '' ||
            normalize(`${fund.ticker} ${fund.name}`).includes(term)
        );
    });
}

function compareNullable(
    a: number | null,
    b: number | null,
    direction: 1 | -1,
): number {
    if (a === null || b === null) {
        return a === b ? 0 : a === null ? 1 : -1;
    }

    return (a - b) * direction;
}

function numberOrNull(value: string | null): number | null {
    return value === null ? null : Number(value);
}

/** Menor P/VP, maior DY 12m, ticker de A a Z, maior patrimônio. Dado ausente vai para o fim. */
export function sortFunds(funds: FundListItem[], key: SortKey): FundListItem[] {
    return [...funds].sort((a, b) => {
        const byKey =
            key === 'ticker'
                ? a.ticker.localeCompare(b.ticker, 'pt-BR')
                : key === 'priceToBook'
                  ? compareNullable(
                        numberOrNull(a.priceToBook),
                        numberOrNull(b.priceToBook),
                        1,
                    )
                  : compareNullable(
                        numberOrNull(
                            key === 'dividendYield'
                                ? a.dividendYield12m
                                : a.netAssets,
                        ),
                        numberOrNull(
                            key === 'dividendYield'
                                ? b.dividendYield12m
                                : b.netAssets,
                        ),
                        -1,
                    );

        return byKey !== 0 ? byKey : a.ticker.localeCompare(b.ticker, 'pt-BR');
    });
}

/**
 * Rendimento por cota dos últimos 12 meses ÷ taxa anual desejada.
 * `annualRate` em pontos percentuais (10 = 10% ao ano) e maior que zero.
 */
export function ceilingPrice(
    dividends: { month: string; perShare: string }[],
    annualRate: number,
): { value: number } | { missingMonths: number } {
    if (dividends.length < CEILING_MONTHS) {
        return { missingMonths: CEILING_MONTHS - dividends.length };
    }

    const total = dividends
        .slice(-CEILING_MONTHS)
        .reduce((sum, dividend) => sum + Number(dividend.perShare), 0);

    return { value: total / (annualRate / 100) };
}

/* Filtros na URL: o voltar do navegador devolve a busca. */

export const SORT_PARAMS: Record<string, SortKey> = {
    pvp: 'priceToBook',
    dy: 'dividendYield',
    ticker: 'ticker',
    patrimonio: 'netAssets',
};

const BASE_PARAMS: Record<string, BaseFilter> = {
    '10': 'base10',
    '100': 'base100',
};

export const DEFAULT_SORT: SortKey = 'priceToBook';

export function readFilters(search: string): Filters & { sort: SortKey } {
    const params = new URLSearchParams(search);
    const kind = params.get('tipo');

    return {
        query: params.get('q') ?? '',
        kind: kind !== null && kind in KIND_LABELS ? (kind as FundKind) : 'all',
        base: BASE_PARAMS[params.get('base') ?? ''] ?? 'all',
        sort: SORT_PARAMS[params.get('ordem') ?? ''] ?? DEFAULT_SORT,
    };
}

export function writeFilters({
    query,
    kind,
    base,
    sort,
}: Filters & { sort: SortKey }): string {
    const params = new URLSearchParams();
    const sortParam = Object.keys(SORT_PARAMS).find(
        (param) => SORT_PARAMS[param] === sort,
    );

    if (query.trim() !== '') {
        params.set('q', query.trim());
    }

    if (kind !== 'all') {
        params.set('tipo', kind);
    }

    if (base !== 'all') {
        params.set('base', base === 'base10' ? '10' : '100');
    }

    if (sort !== DEFAULT_SORT && sortParam) {
        params.set('ordem', sortParam);
    }

    const text = params.toString();

    return text === '' ? '' : `?${text}`;
}

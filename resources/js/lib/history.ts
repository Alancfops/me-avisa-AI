export type Period = 3 | 6 | 12;

export type HistoryPoint = {
    date: string;
    close: string;
    priceToBook: string | null;
};

export const PERIODS: Period[] = [3, 6, 12];

/** Quantos dias sem fechamento no começo da janela ainda contam como janela cheia (fim de semana, feriado). */
const GAP_TOLERANCE_DAYS = 5;

const DAY_IN_MS = 24 * 60 * 60 * 1000;

/** "2026-09-15" em milissegundos UTC, para o eixo numérico dos gráficos. */
export function toTime(date: string): number {
    return Date.parse(`${date}T00:00:00Z`);
}

/** Dia (AAAA-MM-DD) que está `months` meses antes de `lastDay`, sem estourar o fim do mês. */
export function periodStart(lastDay: string, months: Period): string {
    const [year, month, day] = lastDay.split('-').map(Number);
    const target = new Date(Date.UTC(year, month - 1 - months, 1));
    const daysInTarget = new Date(
        Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0),
    ).getUTCDate();
    target.setUTCDate(Math.min(day, daysInTarget));

    return target.toISOString().slice(0, 10);
}

/** Pontos do período, terminando no último fechamento guardado. */
export function inPeriod<T extends { date: string }>(
    history: T[],
    months: Period,
): T[] {
    if (history.length === 0) {
        return [];
    }

    const start = periodStart(history[history.length - 1].date, months);

    return history.filter((point) => point.date >= start);
}

/** Média dos fechamentos; `null` sem pontos. */
export function periodAverage(points: { close: string }[]): number | null {
    if (points.length === 0) {
        return null;
    }

    return (
        points.reduce((sum, point) => sum + Number(point.close), 0) /
        points.length
    );
}

/** Variação em pontos percentuais entre dois preços. */
export function periodChange(first: string, last: string): number {
    return (Number(last) / Number(first) - 1) * 100;
}

/** O app ainda não guarda tanto histórico quanto o período pede. */
export function isIncomplete(
    history: { date: string }[],
    months: Period,
): boolean {
    if (history.length === 0) {
        return true;
    }

    const start = periodStart(history[history.length - 1].date, months);
    const gap = (toTime(history[0].date) - toTime(start)) / DAY_IN_MS;

    return gap > GAP_TOLERANCE_DAYS;
}

/** Último fechamento de cada mês, para a tabela de leitor de tela. */
export function monthlyLast<T extends { date: string }>(points: T[]): T[] {
    const byMonth = new Map<string, T>();

    for (const point of points) {
        byMonth.set(point.date.slice(0, 7), point);
    }

    return [...byMonth.values()];
}

/** Quatro marcas espalhadas do começo ao fim do eixo. */
export function axisTicks(start: number, end: number, count = 4): number[] {
    if (end <= start) {
        return [start];
    }

    return Array.from(
        { length: count },
        (_, i) => start + ((end - start) * i) / (count - 1),
    );
}

/** Limites do eixo vertical com folga, incluindo os valores de referência. */
export function paddedDomain(values: number[]): [number, number] {
    const min = Math.min(...values);
    const max = Math.max(...values);
    const pad = (max - min) * 0.1 || Math.abs(max) * 0.02 || 1;

    return [min - pad, max + pad];
}

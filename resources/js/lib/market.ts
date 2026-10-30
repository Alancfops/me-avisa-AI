const ZONE = 'America/Sao_Paulo';

/** Com brapi a cada 30 min e atraso da própria fonte, até 1 h é o normal. */
const LATE_AFTER_MINUTES = 60;

const OPEN_MINUTE = 10 * 60;
const CLOSE_MINUTE = 18 * 60;
const DAY_IN_MS = 24 * 60 * 60 * 1000;

export type QuoteFreshness = 'fresh' | 'closed' | 'late';

function dayKey(date: Date): string {
    return date.toLocaleDateString('en-CA', { timeZone: ZONE });
}

function clock(date: Date): string {
    return date.toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: ZONE,
    });
}

function isMarketOpen(now: Date): boolean {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: ZONE,
        weekday: 'short',
        hour: 'numeric',
        minute: 'numeric',
        hourCycle: 'h23',
    }).formatToParts(now);
    const part = (type: string) =>
        parts.find((item) => item.type === type)?.value ?? '';
    const minute = Number(part('hour')) * 60 + Number(part('minute'));

    return (
        !['Sat', 'Sun'].includes(part('weekday')) &&
        minute >= OPEN_MINUTE &&
        minute < CLOSE_MINUTE
    );
}

export function isToday(iso: string, now = new Date()): boolean {
    return dayKey(new Date(iso)) === dayKey(now);
}

/**
 * Sem calendário de feriados: num feriado em horário de pregão a cotação aparece
 * como sem atualizar, o que continua verdadeiro.
 */
export function quoteFreshness(iso: string, now = new Date()): QuoteFreshness {
    if (!isMarketOpen(now)) {
        return 'closed';
    }

    const minutes = (now.getTime() - new Date(iso).getTime()) / 60000;

    return minutes > LATE_AFTER_MINUTES ? 'late' : 'fresh';
}

/** "14:15", "sexta, 17:55" ou "27/10, 17:55". */
export function quoteMoment(iso: string, now = new Date()): string {
    const date = new Date(iso);

    if (isToday(iso, now)) {
        return clock(date);
    }

    if (now.getTime() - date.getTime() < 6 * DAY_IN_MS) {
        const weekday = date
            .toLocaleDateString('pt-BR', { weekday: 'long', timeZone: ZONE })
            .replace('-feira', '');

        return `${weekday}, ${clock(date)}`;
    }

    const day = date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        timeZone: ZONE,
    });

    return `${day}, ${clock(date)}`;
}

/** "Quinta, 29 de outubro". */
export function longDate(now = new Date()): string {
    const text = now
        .toLocaleDateString('pt-BR', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            timeZone: ZONE,
        })
        .replace('-feira', '');

    return text.charAt(0).toUpperCase() + text.slice(1);
}

/** Separa os avisos por dia: "Hoje", "Ontem" ou "27 de outubro". */
export function dayHeading(iso: string, now = new Date()): string {
    const date = new Date(iso);

    if (dayKey(date) === dayKey(now)) {
        return 'Hoje';
    }

    if (dayKey(date) === dayKey(new Date(now.getTime() - DAY_IN_MS))) {
        return 'Ontem';
    }

    return date.toLocaleDateString('pt-BR', {
        day: 'numeric',
        month: 'long',
        year:
            dayKey(date).slice(0, 4) === dayKey(now).slice(0, 4)
                ? undefined
                : 'numeric',
        timeZone: ZONE,
    });
}

export function dayGroupKey(iso: string): string {
    return dayKey(new Date(iso));
}

export function timeOfDay(iso: string): string {
    return clock(new Date(iso));
}

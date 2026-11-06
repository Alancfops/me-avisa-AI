export function brl(value: string): string {
    return Number(value).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

export function signedPercent(value: string): string {
    const number = Number(value);
    const sign = number > 0 ? '+' : number < 0 ? '−' : '';

    return `${sign}${Math.abs(number).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}%`;
}

export function clockTime(iso: string): string {
    return new Date(iso).toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'America/Sao_Paulo',
    });
}

export function dateTime(iso: string): string {
    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'America/Sao_Paulo',
    });
}

export function ratio(value: string): string {
    return Number(value).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export function percentFromFraction(value: string): string {
    return `${(Number(value) * 100).toLocaleString('pt-BR', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    })}%`;
}

const months = [
    'janeiro',
    'fevereiro',
    'março',
    'abril',
    'maio',
    'junho',
    'julho',
    'agosto',
    'setembro',
    'outubro',
    'novembro',
    'dezembro',
];

export function monthLabel(yearMonth: string): string {
    const [year, month] = yearMonth.split('-');

    return `${months[Number(month) - 1]}/${year}`;
}

const shortMonths = [
    'jan',
    'fev',
    'mar',
    'abr',
    'mai',
    'jun',
    'jul',
    'ago',
    'set',
    'out',
    'nov',
    'dez',
];

/** "2026-09" vira "set/26". */
export function shortMonthLabel(yearMonth: string): string {
    const [year, month] = yearMonth.split('-');

    return `${shortMonths[Number(month) - 1]}/${year.slice(2)}`;
}

/** "2026-09-15" vira "15/09". */
export function dayMonth(date: string): string {
    const [, month, day] = date.split('-');

    return `${day}/${month}`;
}

/** "2026-09-15" vira "15/09/26": sem ambiguidade em janelas que cruzam o ano. */
export function dayMonthYear(date: string): string {
    const [year, month, day] = date.split('-');

    return `${day}/${month}/${year.slice(2)}`;
}

/** Valores grandes sem zeros demais: "R$ 4,62 bi". */
export function compactBrl(value: string): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        notation: 'compact',
        maximumFractionDigits: 2,
    }).format(Number(value));
}

/** Contagens grandes sem zeros demais: "301 mil". */
export function compactNumber(value: number): string {
    return new Intl.NumberFormat('pt-BR', { notation: 'compact' }).format(
        value,
    );
}

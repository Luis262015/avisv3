const MONEDAS: Record<string, string> = { BOB: 'Bs', USD: '$us' };

/** «Bs 1.990» o «Bs 199,50»: sin decimales cuando el importe es entero. */
export function money(amount: number, currency = 'BOB'): string {
    const entero = Number.isInteger(amount);

    return `${MONEDAS[currency] ?? currency} ${amount.toLocaleString('es-BO', {
        minimumFractionDigits: entero ? 0 : 2,
        maximumFractionDigits: 2,
    })}`;
}

/** «7 de octubre de 2026». */
export function longDate(iso: string | null | undefined): string {
    if (!iso) return '—';

    return new Date(iso).toLocaleDateString('es-BO', { day: 'numeric', month: 'long', year: 'numeric' });
}

/** «07/10/2026». */
export function shortDate(iso: string | null | undefined): string {
    if (!iso) return '—';

    return new Date(iso).toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

/** Cupo de un plan: «3», «3.000» o «Sin límite». */
export function limit(value: number | null): string {
    return value === null ? 'Sin límite' : value.toLocaleString('es-BO');
}

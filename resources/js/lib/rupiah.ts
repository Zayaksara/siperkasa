/**
 * Raw form value ("1500000.50") from what the user typed ("1.500.000,5").
 *
 * Comma is the decimal separator. Without a comma, a lone trailing dot with
 * 1-2 digits ("1500000.50") counts as a decimal point when `dotDecimal` is on
 * (pastes); otherwise dots are thousands separators ("1.500.000").
 */
export function parseRupiah(input: string, dotDecimal = true): string {
    const cleaned = input.replace(/[^\d,.]/g, '');
    let whole: string;
    let fraction: string | null = null;

    if (cleaned.includes(',')) {
        const [head, ...rest] = cleaned.split(',');
        whole = head.replace(/\./g, '');
        fraction = rest.join('').replace(/\./g, '');
    } else if (dotDecimal && /^\d*\.\d{1,2}$/.test(cleaned)) {
        [whole, fraction] = cleaned.split('.');
    } else {
        whole = cleaned.replace(/\./g, '');
    }

    const digits = whole.replace(/^0+(?=\d)/, '');
    const decimals = (fraction ?? '').slice(0, 2);

    if (decimals === '') return fraction === null ? digits : digits || '0';

    return `${digits === '' ? '0' : digits}.${decimals}`;
}

/** Display text ("1.500.000,5") from a raw value ("1500000.5"). */
export function formatRupiahInput(raw: string): string {
    if (raw === '') return '';

    const [whole, fraction] = raw.split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return fraction === undefined ? grouped : `${grouped},${fraction}`;
}

/** Caret index in `formatted` that sits after the same count of digits/comma as `caret` in `typed`. */
export function caretAfter(
    typed: string,
    caret: number,
    formatted: string,
): number {
    const significant = (typed.slice(0, caret).match(/[\d,]/g) ?? []).length;
    if (significant === 0) return 0;

    let seen = 0;
    for (let i = 0; i < formatted.length; i++) {
        if (/[\d,]/.test(formatted[i]) && ++seen === significant) return i + 1;
    }

    return formatted.length;
}

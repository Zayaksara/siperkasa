/** Raw form value ("1500000.50") from what the user typed ("1.500.000,5"). */
export function parseRupiah(input: string): string {
    const cleaned = input.replace(/[^\d,]/g, '');
    const [whole, ...rest] = cleaned.split(',');
    const digits = whole.replace(/^0+(?=\d)/, '');

    if (rest.length === 0) return digits;

    return `${digits === '' ? '0' : digits}.${rest.join('').slice(0, 2)}`;
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

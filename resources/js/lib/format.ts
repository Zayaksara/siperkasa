const zone = 'Asia/Jakarta';

export function formatDate(iso?: string | null): string {
    if (!iso) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: zone,
    }).format(new Date(iso));
}

export function formatDateTime(iso?: string | null): string {
    if (!iso) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: zone,
    }).format(new Date(iso));
}

export function formatRupiah(value?: number | string | null): string {
    if (value === null || value === undefined || value === '') return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

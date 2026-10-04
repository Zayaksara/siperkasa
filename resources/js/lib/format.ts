const zone = 'Asia/Jakarta';

export function formatDate(iso?: string | null): string {
    if (!iso) return '-';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: zone,
    }).format(d);
}

export function formatDateTime(iso?: string | null): string {
    if (!iso) return '-';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: zone,
    }).format(d);
}

export function formatRupiah(value?: number | string | null): string {
    if (value === null || value === undefined || value === '') return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

/** "Sisa 2 hari 3 jam" / "Terlambat 5 jam" relative to `now` (ms). Null when no SLA. */
export function formatSlaRemaining(
    iso: string | null | undefined,
    now: number,
): string | null {
    if (!iso) return null;
    const target = new Date(iso).getTime();
    if (isNaN(target)) return null;
    const late = target < now;
    const minutes = Math.floor(Math.abs(target - now) / 60000);
    const days = Math.floor(minutes / 1440);
    const hours = Math.floor((minutes % 1440) / 60);
    let span: string;
    if (days > 0)
        span = hours > 0 ? `${days} hari ${hours} jam` : `${days} hari`;
    else if (hours > 0) span = `${hours} jam`;
    else span = `${Math.max(1, minutes)} menit`;

    return `${late ? 'Terlambat' : 'Sisa'} ${span}`;
}

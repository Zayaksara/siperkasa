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

/** "28 Sep – 4 Okt 2026", "4 Okt 2026" or "28 Des 2025 – 4 Jan 2026" from two YYYY-MM-DD dates. */
export function formatDateRange(from: string, to: string): string {
    const parts = (iso: string) => {
        const d = new Date(`${iso}T00:00:00Z`);
        const fmt = (o: Intl.DateTimeFormatOptions) =>
            new Intl.DateTimeFormat('id-ID', { ...o, timeZone: 'UTC' }).format(
                d,
            );
        return {
            day: fmt({ day: 'numeric' }),
            month: fmt({ month: 'short' }),
            year: fmt({ year: 'numeric' }),
        };
    };
    const a = parts(from);
    const b = parts(to);
    if (from === to) return `${b.day} ${b.month} ${b.year}`;
    const start =
        a.year === b.year
            ? `${a.day} ${a.month}`
            : `${a.day} ${a.month} ${a.year}`;
    return `${start} – ${b.day} ${b.month} ${b.year}`;
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
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(value));
}

/** "2 hari" / "1,5 hari" from 48 h up, "5,5 jam" below; "–" when there is nothing to average. */
export function formatHours(hours?: number | null): string {
    if (hours === null || hours === undefined || isNaN(hours)) return '–';
    const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });
    return hours >= 48
        ? `${number.format(hours / 24)} hari`
        : `${number.format(hours)} jam`;
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

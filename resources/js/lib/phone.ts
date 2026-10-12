/** Digits only, 62-prefixed, as wa.me expects ("0812…" and "+62812…" both become "62812…"). */
export function toWhatsAppNumber(phone: string): string {
    const digits = phone.replace(/\D/g, '');

    return digits.startsWith('0') ? `62${digits.slice(1)}` : digits;
}

export function whatsAppUrl(phone: string): string {
    return `https://wa.me/${toWhatsAppNumber(phone)}`;
}

export function telUrl(phone: string): string {
    return `tel:${phone.replace(/[^\d+]/g, '')}`;
}

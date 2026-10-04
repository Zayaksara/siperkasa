/**
 * Categorical chart palette: existing UINSSC tokens only (light + dark values live in app.css).
 * Order keeps neighbouring ids visually distinct.
 */
export const ENTITY_PALETTE = [
    '--accent-teal',
    '--accent-amber',
    '--accent-sky',
    '--accent-rose',
    '--accent-violet',
    '--chart-1',
    '--chart-2',
    '--chart-3',
    '--chart-4',
    '--chart-5',
] as const;

/** Colour by position in chart: every bar in one chart differs. Max 10 bars = 10 tokens. */
export function rankColor(index: number): string {
    return ENTITY_PALETTE[index % ENTITY_PALETTE.length];
}

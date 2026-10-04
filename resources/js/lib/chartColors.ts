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

/** Stable colour token per entity id: the same category/building keeps its colour across reloads and filters. */
export function entityColor(id: number): string {
    return ENTITY_PALETTE[Math.abs(Math.trunc(id)) % ENTITY_PALETTE.length];
}

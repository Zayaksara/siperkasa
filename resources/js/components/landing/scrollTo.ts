/**
 * Smooth anchor scroll that honours prefers-reduced-motion and moves focus
 * to the target section so keyboard / screen-reader users land there too.
 */
export function scrollToSection(event: MouseEvent, id: string): void {
    const el = document.getElementById(id);
    if (!el) return;
    event.preventDefault();
    const reduce = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '-1');
    el.focus({ preventScroll: true });
    // Keep Inertia's history.state intact; only the hash changes.
    history.replaceState(history.state, '', `#${id}`);
}

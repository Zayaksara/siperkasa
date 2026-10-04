/**
 * Shared GSAP setup for every page.
 *
 * Rules (see .superpowers/sdd/implementer-instructions.md, "Animation rules"):
 * - Plugins are registered once, on the client only (never during SSR).
 * - Animations are created in onMounted and scoped to a root element.
 * - Everything is reverted in onBeforeUnmount, so Inertia visits between
 *   pages never leak tweens or ScrollTriggers.
 * - Content is visible without JS: elements are hidden only from JS
 *   (gsap.from / fromTo / set), never by CSS defaults.
 * - prefers-reduced-motion: reduce means no animation at all; the page keeps
 *   its natural, final state.
 */
import { onBeforeUnmount, onMounted } from 'vue';
import type { Ref } from 'vue';
// Type-only: erased at build time, so gsap stays out of the main chunk.
import type { gsap } from 'gsap';
import type { ScrollTrigger as ScrollTriggerPlugin } from 'gsap/ScrollTrigger';

/** The lazily loaded GSAP kit. Never import gsap statically outside this file. */
export interface MotionKit {
    gsap: typeof gsap;
    ScrollTrigger: typeof ScrollTriggerPlugin;
}

export const MOTION_OK = '(prefers-reduced-motion: no-preference)';
export const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';
export const DESKTOP = '(min-width: 1024px)';

/** House easing and timing, shared by all pages. */
export const motion = {
    ease: 'power3.out',
    duration: 0.6,
    /** Small entrance offset in px; never more than 16. */
    rise: 16,
    stagger: 0.08,
} as const;

let kit: Promise<MotionKit> | undefined;

/**
 * Loads GSAP and ScrollTrigger once (memoised), registers the plugin and house
 * defaults. Client only: the dynamic import never runs during SSR.
 */
export function loadMotion(): Promise<MotionKit> {
    if (typeof window === 'undefined') {
        return Promise.reject(new Error('GSAP is client only'));
    }
    kit ??= Promise.all([import('gsap'), import('gsap/ScrollTrigger')])
        .then(([core, st]) => {
            core.gsap.registerPlugin(st.ScrollTrigger);
            core.gsap.defaults({
                ease: motion.ease,
                duration: motion.duration,
            });
            return { gsap: core.gsap, ScrollTrigger: st.ScrollTrigger };
        })
        .catch((error) => {
            kit = undefined; // allow a retry after a failed chunk load
            throw error;
        });
    return kit;
}

/** True when the visitor has asked for reduced motion (client only). */
export function prefersReducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia(REDUCED_MOTION).matches
    );
}

export interface MotionConditions extends MotionKit {
    /** Viewport is at least lg (1024px). */
    isDesktop: boolean;
    /**
     * The matchMedia context. Wrap animations created later (e.g. inside
     * ScrollTrigger callbacks) in `context.add(() => ...)` so they are
     * reverted with everything else.
     */
    context: gsap.Context;
}

/**
 * Runs `setup` after mount inside a gsap.matchMedia() scoped to `root`, only
 * when motion is allowed. Re-runs on breakpoint change (lg) and reverts all
 * tweens/ScrollTriggers on unmount or when reduced motion turns on.
 * Selector strings inside `setup` only match elements within `root`.
 */
export function useMotion(
    root: Readonly<Ref<HTMLElement | null>>,
    setup: (conditions: MotionConditions) => void | (() => void),
): void {
    let mm: gsap.MatchMedia | undefined;
    let unmounted = false;

    onMounted(async () => {
        if (!root.value || typeof window === 'undefined') return;
        let loaded: MotionKit;
        try {
            loaded = await loadMotion();
        } catch {
            return; // chunk failed to load: page keeps its static final state
        }
        // Component left (fast navigation) before the import resolved.
        if (unmounted || !root.value) return;
        mm = loaded.gsap.matchMedia(root.value);
        mm.add(
            { motionOk: MOTION_OK, isDesktop: DESKTOP },
            (context) => {
                const { motionOk, isDesktop } = context.conditions as Record<
                    string,
                    boolean
                >;
                if (!motionOk) return;
                return setup({ ...loaded, isDesktop, context });
            },
            root.value,
        );
    });

    onBeforeUnmount(() => {
        unmounted = true;
        mm?.revert();
        mm = undefined;
    });
}

/**
 * Small horizontal shake for error feedback (e.g. failed login). Skipped
 * entirely with reduced motion. Transform only, ends at x: 0.
 */
export function shake(el: Element | null | undefined): void {
    if (!el || typeof window === 'undefined' || prefersReducedMotion()) return;
    void loadMotion()
        .then(({ gsap }) => {
            gsap.fromTo(
                el,
                { x: 0 },
                {
                    keyframes: { x: [0, -6, 6, -4, 4, 0] },
                    duration: 0.4,
                    ease: 'power1.inOut',
                    clearProps: 'transform',
                    overwrite: 'auto',
                },
            );
        })
        .catch(() => {});
}

/**
 * Light staggered entrance for a group of siblings as they scroll into view
 * (feature rows, role columns...). Plays once; elements are hidden only from
 * JS. Call inside a useMotion setup.
 */
export function revealBatch(
    { gsap, ScrollTrigger }: MotionKit,
    targets: Element[],
    context: gsap.Context,
    vars: { y?: number; stagger?: number; start?: string } = {},
): void {
    if (!targets.length) return;
    const y = vars.y ?? motion.rise;
    gsap.set(targets, { autoAlpha: 0, y });
    ScrollTrigger.batch(targets, {
        start: vars.start ?? 'top 88%',
        once: true,
        onEnter: (batch) =>
            context.add(() =>
                gsap.to(batch, {
                    autoAlpha: 1,
                    y: 0,
                    stagger: vars.stagger ?? motion.stagger,
                    overwrite: true,
                }),
            ),
    });
}

/** Single element entrance when it scrolls into view. Plays once. */
export function revealOnScroll(
    { gsap }: MotionKit,
    target: Element | undefined,
    vars: { y?: number; scale?: number; start?: string } = {},
): void {
    if (!target) return;
    gsap.from(target, {
        autoAlpha: 0,
        y: vars.y ?? motion.rise,
        scale: vars.scale ?? 1,
        scrollTrigger: {
            trigger: target,
            start: vars.start ?? 'top 85%',
            once: true,
        },
    });
}

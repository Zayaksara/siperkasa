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
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { onBeforeUnmount, onMounted } from 'vue';
import type { Ref } from 'vue';

export { gsap, ScrollTrigger };

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

let registered = false;

/** Registers plugins and defaults once. Safe to call repeatedly; client only. */
export function setupMotion(): boolean {
    if (typeof window === 'undefined') return false;
    if (!registered) {
        gsap.registerPlugin(ScrollTrigger);
        gsap.defaults({ ease: motion.ease, duration: motion.duration });
        registered = true;
    }
    return true;
}

/** True when the visitor has asked for reduced motion (client only). */
export function prefersReducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia(REDUCED_MOTION).matches
    );
}

export interface MotionConditions {
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

    onMounted(() => {
        if (!root.value || !setupMotion()) return;
        mm = gsap.matchMedia(root.value);
        mm.add(
            { motionOk: MOTION_OK, isDesktop: DESKTOP },
            (context) => {
                const { motionOk, isDesktop } = context.conditions as Record<
                    string,
                    boolean
                >;
                if (!motionOk) return;
                return setup({ isDesktop, context });
            },
            root.value,
        );
    });

    onBeforeUnmount(() => {
        mm?.revert();
        mm = undefined;
    });
}

/**
 * Small horizontal shake for error feedback (e.g. failed login). Skipped
 * entirely with reduced motion. Transform only, ends at x: 0.
 */
export function shake(el: Element | null | undefined): void {
    if (!el || !setupMotion() || prefersReducedMotion()) return;
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
}

/**
 * Light staggered entrance for a group of siblings as they scroll into view
 * (feature rows, role columns...). Plays once; elements are hidden only from
 * JS. Call inside a useMotion setup.
 */
export function revealBatch(
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

<script setup lang="ts">
import { onMounted, ref, useTemplateRef, watch } from 'vue';
import { useMotion } from '@/lib/motion';
import type { MotionConditions } from '@/lib/motion';

/**
 * Doodle illustration from getillustrations.com (free licence, attribution
 * shown on the error page). Only the SVG for the requested status is fetched
 * (lazy import), then recoloured to landing tokens through the `.a` (accent) and
 * `.p` (paper) classes; strokes inherit `fill` from the root. Local, trusted
 * files, so v-html is safe.
 */
const props = defineProps<{ status: number }>();

// Explicit lazy imports (not a glob) so only these six files are emitted.
const LOADERS: Record<number, () => Promise<{ default: string }>> = {
    403: () =>
        import('../assets/errors/character-struggling-with-door.svg?raw'),
    404: () =>
        import('../assets/errors/character-distracted-by-phone-mishap.svg?raw'),
    419: () =>
        import('../assets/errors/character-reading-newspaper-with-coffee.svg?raw'),
    429: () => import('../assets/errors/person-slipping-on-floor.svg?raw'),
    500: () => import('../assets/errors/dropped-ice-cream-cone.svg?raw'),
    503: () => import('../assets/errors/person-working-on-laptop.svg?raw'),
};

function recolor(raw: string): string {
    return raw
        .replace(/<\?xml[^>]*\?>\s*/, '')
        .replace(
            /<svg[^>]*>/,
            '<svg class="err-art" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600" focusable="false">',
        )
        .replace(/fill="#(?:aaca60|00b4e9)"/g, 'class="a"')
        .replace(/fill="#fff"/g, 'class="p"');
}

const html = ref('');
const root = useTemplateRef<HTMLElement>('root');

let kit: MotionConditions | undefined;
const kitReady = ref(false);

onMounted(async () => {
    // Any other code falls back to the 500 picture.
    const load = LOADERS[props.status] ?? LOADERS[500];
    try {
        html.value = recolor((await load()).default);
    } catch {
        // chunk failed: the card still works without the picture
    }
});

useMotion(root, (conditions) => {
    kit = conditions;
    kitReady.value = true;
    return () => {
        kit = undefined;
        kitReady.value = false;
    };
});

// Start the fade-in and idle once both the SVG markup and GSAP are present.
// context.add keeps the tweens inside the matchMedia context, so they are
// reverted on unmount or when reduced motion turns on.
watch(
    [html, kitReady],
    () => {
        if (!kit || !html.value || !root.value) return;
        const { gsap, context } = kit;
        const host = root.value;
        context.add(() => {
            const svg = host.querySelector('.err-art');
            if (!svg) return;
            gsap.from(svg, { autoAlpha: 0, y: 12, duration: 0.4 });
            gsap.to(svg, {
                y: -6,
                duration: 2.6,
                ease: 'sine.inOut',
                repeat: -1,
                yoyo: true,
            });
            // Small parallax drift on inner groups; transform only.
            const inner = svg.querySelectorAll('g g');
            if (!inner.length) return;
            gsap.to(inner, {
                x: () => gsap.utils.random(-1.5, 1.5),
                y: () => gsap.utils.random(-1.5, 1.5),
                duration: () => gsap.utils.random(2.2, 3.4),
                ease: 'sine.inOut',
                repeat: -1,
                yoyo: true,
            });
        });
    },
    { flush: 'post' },
);
</script>

<template>
    <div
        ref="root"
        class="err-illustration aspect-square w-full"
        aria-hidden="true"
        v-html="html"
    />
</template>

<style scoped>
.err-illustration :deep(.err-art) {
    display: block;
    width: 100%;
    height: 100%;
    fill: var(--landing-fg);
    overflow: visible;
}
.err-illustration :deep(.a) {
    fill: var(--landing-glow);
}
.err-illustration :deep(.p) {
    fill: var(--landing-surface);
}
</style>

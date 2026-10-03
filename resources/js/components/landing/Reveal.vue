<script setup lang="ts">
import {
    usePreferredReducedMotion,
    useIntersectionObserver,
} from '@vueuse/core';
import type { CSSProperties } from 'vue';
import { computed, onBeforeUnmount, ref, useTemplateRef } from 'vue';

/**
 * Scroll-reveal wrapper. The entrance is driven by inline styles only and the
 * styles are removed once it finishes, so caller classes (hover lift,
 * transition-*) are never overridden or delayed by the entrance timing.
 */
const props = withDefaults(
    defineProps<{
        as?: string;
        delay?: number;
        duration?: number;
        from?: 'up' | 'left' | 'right' | 'scale';
    }>(),
    { as: 'div', delay: 0, duration: 300, from: 'up' },
);

const target = useTemplateRef<HTMLElement>('target');
const reduced = usePreferredReducedMotion();
const phase = ref<'hidden' | 'entering' | 'done'>('hidden');
let timer: ReturnType<typeof setTimeout> | undefined;

function finish(): void {
    clearTimeout(timer);
    phase.value = 'done';
}

const { stop } = useIntersectionObserver(
    target,
    (entries) => {
        if (!entries[0]?.isIntersecting) return;
        stop();
        phase.value = 'entering';
        // Fallback in case transitionend never fires (e.g. tab hidden).
        timer = setTimeout(finish, props.delay + props.duration + 100);
    },
    { threshold: 0.12, rootMargin: '0px 0px -6% 0px' },
);

onBeforeUnmount(() => clearTimeout(timer));

function onTransitionEnd(event: TransitionEvent): void {
    if (
        event.target === event.currentTarget &&
        event.propertyName === 'opacity' &&
        phase.value === 'entering'
    ) {
        finish();
    }
}

const offsets = {
    up: { translate: '0 1.5rem', scale: '1' },
    left: { translate: '-1.5rem 0', scale: '1' },
    right: { translate: '1.5rem 0', scale: '1' },
    scale: { translate: '0 1rem', scale: '0.97' },
} as const;

const style = computed<CSSProperties>(() => {
    if (reduced.value === 'reduce' || phase.value === 'done') return {};
    if (phase.value === 'hidden') {
        return { opacity: 0, ...offsets[props.from] };
    }
    const t = `${props.duration}ms cubic-bezier(0.22, 1, 0.36, 1) ${props.delay}ms`;
    return {
        opacity: 1,
        translate: '0 0',
        scale: '1',
        transition: `opacity ${t}, translate ${t}, scale ${t}`,
    };
});
</script>

<template>
    <component
        :is="as"
        ref="target"
        :style="style"
        @transitionend="onTransitionEnd"
    >
        <slot />
    </component>
</template>

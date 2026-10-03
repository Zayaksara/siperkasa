<script setup lang="ts">
import {
    usePreferredReducedMotion,
    useIntersectionObserver,
} from '@vueuse/core';
import { computed, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';

const props = withDefaults(
    defineProps<{ value: number; duration?: number }>(),
    { duration: 1400 },
);

const fmt = new Intl.NumberFormat('id-ID');
const target = useTemplateRef<HTMLElement>('target');
const reduced = usePreferredReducedMotion();
const end = computed(() => Math.max(0, Math.round(props.value || 0)));
const current = ref(0);
const started = ref(false);
let frame = 0;

const display = computed(() => fmt.format(Math.round(current.value)));
const finalText = computed(() => fmt.format(end.value));

function animateTo(to: number): void {
    cancelAnimationFrame(frame);
    if (reduced.value === 'reduce') {
        current.value = to;
        return;
    }
    const from = current.value;
    const start = performance.now();
    const tick = (now: number): void => {
        const t = Math.min(1, (now - start) / props.duration);
        current.value = from + (to - from) * (1 - Math.pow(1 - t, 3));
        if (t < 1) frame = requestAnimationFrame(tick);
    };
    frame = requestAnimationFrame(tick);
}

const { stop } = useIntersectionObserver(
    target,
    (entries) => {
        if (!entries[0]?.isIntersecting) return;
        stop();
        started.value = true;
        animateTo(end.value);
    },
    { threshold: 0.4 },
);

// Prop changes after the first run (e.g. Inertia reload) update the number.
watch(end, (to) => {
    if (started.value) animateTo(to);
});

onBeforeUnmount(() => cancelAnimationFrame(frame));
</script>

<template>
    <span ref="target" class="tabular-nums">
        <span aria-hidden="true">{{
            reduced === 'reduce' ? finalText : display
        }}</span>
        <span class="sr-only">{{ finalText }}</span>
    </span>
</template>

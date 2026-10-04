<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { computed } from 'vue';

const props = defineProps<{
    label: string;
    value: number | string;
    href?: string;
    icon?: Component;
    accent?: 'teal' | 'amber' | 'sky' | 'rose' | 'violet';
    hint?: string;
}>();

const tile = computed(
    () =>
        ({
            teal: 'bg-accent-teal-bg text-accent-teal',
            amber: 'bg-accent-amber-bg text-accent-amber',
            sky: 'bg-accent-sky-bg text-accent-sky',
            rose: 'bg-accent-rose-bg text-accent-rose',
            violet: 'bg-accent-violet-bg text-accent-violet',
        })[props.accent ?? 'teal'],
);
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="flex min-h-24 flex-col gap-3 rounded-xl border bg-card p-4"
        :class="
            href
                ? 'hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none'
                : ''
        "
    >
        <span class="flex items-center gap-3">
            <span
                v-if="icon"
                class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                :class="tile"
                aria-hidden="true"
            >
                <component :is="icon" class="size-5" />
            </span>
            <span class="text-sm text-muted-foreground">{{ label }}</span>
        </span>
        <span class="text-3xl font-bold tabular-nums">{{ value }}</span>
        <span v-if="hint" class="text-xs text-muted-foreground">{{
            hint
        }}</span>
    </component>
</template>

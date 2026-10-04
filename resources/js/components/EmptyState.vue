<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    description?: string;
    icon?: Component;
    accent?: 'teal' | 'amber' | 'sky' | 'rose' | 'violet';
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
    <div
        class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border p-8 text-center"
    >
        <span
            v-if="icon"
            class="flex size-12 items-center justify-center rounded-full"
            :class="tile"
            aria-hidden="true"
        >
            <component :is="icon" class="size-6" />
        </span>
        <p class="font-medium">{{ title }}</p>
        <p v-if="description" class="max-w-sm text-sm text-muted-foreground">
            {{ description }}
        </p>
        <div class="mt-2"><slot /></div>
    </div>
</template>

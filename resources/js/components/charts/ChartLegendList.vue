<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    items: { label: string; value: number; href?: string; color: string }[];
    unit?: string;
}>();
</script>

<template>
    <ul class="grid gap-1 sm:grid-cols-2">
        <li v-for="item in items" :key="item.label">
            <component
                :is="item.href ? Link : 'div'"
                :href="item.href"
                class="flex min-h-11 items-center gap-3 rounded-md px-2 text-sm"
                :class="
                    item.href
                        ? 'hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none'
                        : ''
                "
            >
                <span
                    class="size-3 shrink-0 rounded-sm"
                    :style="{ backgroundColor: `var(${item.color})` }"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1 truncate" :title="item.label">{{
                    item.label
                }}</span>
                <span
                    class="shrink-0 font-semibold whitespace-nowrap tabular-nums"
                >
                    {{ item.value }}
                    <span
                        v-if="unit"
                        class="font-normal text-muted-foreground"
                        >{{ unit }}</span
                    >
                </span>
            </component>
        </li>
    </ul>
</template>

<script setup lang="ts">
import { cn } from '@/lib/utils';

const model = defineModel<string | number | null>({ default: '' });
defineProps<{ class?: string }>();
</script>

<template>
    <select
        v-model="model"
        :class="
            cn(
                'h-11 w-full rounded-md border border-input bg-background px-3 text-base shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                $props.class,
            )
        "
    >
        <slot />
    </select>
</template>

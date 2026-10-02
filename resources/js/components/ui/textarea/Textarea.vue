<script setup lang="ts">
import { cn } from '@/lib/utils';

const model = defineModel<string>({ default: '' });
defineProps<{ class?: string; rows?: number }>();
</script>

<template>
    <textarea
        v-model="model"
        :rows="rows ?? 4"
        :class="
            cn(
                'flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                $props.class,
            )
        "
    />
</template>

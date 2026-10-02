<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div
        class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border p-8 text-center"
    >
        <p class="font-medium">{{ title }}</p>
        <p v-if="description" class="max-w-sm text-sm text-muted-foreground">
            {{ description }}
        </p>
        <div class="mt-2"><slot /></div>
    </div>
</template>

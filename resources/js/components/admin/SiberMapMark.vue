<script setup lang="ts">
import { Lock } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';

withDefaults(defineProps<{ lock?: boolean }>(), { lock: false });
</script>

<template>
    <span
        v-if="lock"
        class="inline-flex size-11 shrink-0 items-center justify-center rounded-md border text-muted-foreground"
        title="Dikelola SiberMap"
        role="img"
        aria-label="Dikelola SiberMap, tidak bisa diubah atau dihapus"
    >
        <Lock class="size-4" />
    </span>
    <Badge
        v-else
        variant="outline"
        class="border-primary/30 bg-primary-soft text-foreground"
        >SiberMap</Badge
    >
</template>

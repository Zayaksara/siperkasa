<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDateTime } from '@/lib/format';

defineProps<{
    items: {
        id: number;
        to_status: string;
        to_label: string;
        actor: string | null;
        note: string | null;
        created_at: string;
    }[];
}>();
</script>

<template>
    <ol
        class="relative grid gap-4 border-l border-border pl-5"
        aria-label="Riwayat status"
    >
        <li v-for="item in items" :key="item.id" class="relative">
            <span
                class="absolute top-1.5 -left-[1.6rem] size-3 rounded-full border-2 border-background bg-primary"
                aria-hidden="true"
            />
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge :status="item.to_status" />
                <time
                    class="text-xs text-muted-foreground"
                    :datetime="item.created_at"
                    >{{ formatDateTime(item.created_at) }}</time
                >
            </div>
            <p v-if="item.actor" class="mt-1 text-sm text-muted-foreground">
                oleh {{ item.actor }}
            </p>
            <p v-if="item.note" class="mt-1 text-sm">{{ item.note }}</p>
        </li>
    </ol>
</template>

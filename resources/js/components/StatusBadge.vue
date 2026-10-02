<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ status: string; label?: string }>();

const map: Record<string, { label: string; cls: string }> = {
    Pending: {
        label: 'Menunggu',
        cls: 'bg-status-pending-bg text-status-pending',
    },
    Assigned: {
        label: 'Ditugaskan',
        cls: 'bg-status-assigned-bg text-status-assigned',
    },
    In_Progress: {
        label: 'Dikerjakan',
        cls: 'bg-status-in-progress-bg text-status-in-progress',
    },
    On_Hold: {
        label: 'Ditunda',
        cls: 'bg-status-on-hold-bg text-status-on-hold',
    },
    Closed: { label: 'Selesai', cls: 'bg-status-closed-bg text-status-closed' },
    Rejected: {
        label: 'Ditolak',
        cls: 'bg-status-rejected-bg text-status-rejected',
    },
    Cancelled: {
        label: 'Dibatalkan',
        cls: 'bg-status-cancelled-bg text-status-cancelled',
    },
};

const entry = computed(
    () =>
        map[props.status] ?? {
            label: props.status,
            cls: 'bg-muted text-muted-foreground',
        },
);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap"
        :class="entry.cls"
        data-test="status-badge"
    >
        {{ label ?? entry.label }}
    </span>
</template>

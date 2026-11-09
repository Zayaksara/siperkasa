<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';

defineProps<{
    performance: unknown;
    range: { from: string; to: string };
}>();
</script>

<template>
    <Head title="Kinerja PIC" />

    <div class="space-y-6">
        <PageHeader
            title="Kinerja PIC"
            description="Ketepatan waktu penyelesaian tugas per PIC."
        />
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ReportForm from '@/components/ReportForm.vue';
import { create, index } from '@/routes/reports';

defineProps<{
    categories: { id: number; name: string }[];
    buildings: {
        id: number;
        code: string;
        name: string;
        rooms: { id: number; code: string; name: string }[];
    }[];
    priorities: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Laporan Saya', href: index() },
            { title: 'Buat Laporan', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Buat Laporan" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
        <PageHeader
            title="Buat Laporan"
            description="Laporkan kerusakan aset. Tim akan memvalidasi dan menugaskan PIC."
        />
        <ReportForm
            mode="create"
            :categories="categories"
            :buildings="buildings"
            :priorities="priorities"
        />
    </div>
</template>

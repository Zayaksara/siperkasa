<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import ReportForm from '@/components/ReportForm.vue';
import { index } from '@/routes/reports';

defineProps<{
    categories: { id: number; name: string }[];
    buildings: {
        id: number;
        code: string;
        name: string;
        rooms: { id: number; code: string; name: string }[];
    }[];
    priorities: { value: string; label: string }[];
    report: {
        id: number;
        ticket_number: string;
        title: string;
        category_id: number;
        room_id: number;
        description: string;
        user_priority: string;
        asset: {
            id: number;
            asset_code: string;
            name: string;
            brand: string | null;
            category: string | null;
        } | null;
        damage_photos: { id: number; url: string }[];
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Laporan Saya', href: index() }] },
});
</script>

<template>
    <Head title="Ubah Laporan" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
        <PageHeader
            :title="`Ubah ${report.ticket_number}`"
            description="Laporan hanya bisa diubah selama masih menunggu validasi."
        />
        <ReportForm
            mode="edit"
            :categories="categories"
            :buildings="buildings"
            :priorities="priorities"
            :report="report"
        />
    </div>
</template>

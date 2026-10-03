<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/format';
import { create, index, show } from '@/routes/reports';

type Row = {
    id: number;
    ticket_number: string;
    title: string;
    status: string;
    is_overdue: boolean;
    created_at: string;
    category: string | null;
    room: string | null;
    building: string | null;
};

const props = defineProps<{
    reports: {
        data: Row[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { status?: string; q?: string };
    statuses: { value: string; label: string }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Laporan Saya', href: index() }] },
});

const q = ref(props.filters.q ?? '');

function filter(status?: string) {
    router.get(
        index.url(),
        { status: status || undefined, q: q.value || undefined },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Laporan Saya" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader
            title="Laporan Saya"
            description="Pantau status semua laporan yang Anda buat."
        >
            <Button as-child class="h-11"
                ><Link :href="create()"
                    ><Plus class="size-4" /> Buat laporan</Link
                ></Button
            >
        </PageHeader>

        <form class="flex gap-2" @submit.prevent="filter(filters.status)">
            <Input
                v-model="q"
                class="h-11"
                placeholder="Cari nomor tiket atau judul"
                aria-label="Cari laporan"
            />
            <Button type="submit" variant="outline" class="h-11">Cari</Button>
        </form>

        <div
            class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1"
            role="group"
            aria-label="Filter status"
        >
            <Button
                size="sm"
                class="h-11 shrink-0"
                :variant="!filters.status ? 'default' : 'outline'"
                :aria-pressed="!filters.status"
                @click="filter()"
                >Semua</Button
            >
            <Button
                v-for="status in statuses"
                :key="status.value"
                size="sm"
                class="h-11 shrink-0"
                :variant="
                    filters.status === status.value ? 'default' : 'outline'
                "
                :aria-pressed="filters.status === status.value"
                @click="filter(status.value)"
            >
                {{ status.label }}
            </Button>
        </div>

        <EmptyState
            v-if="reports.data.length === 0"
            title="Belum ada laporan"
            description="Laporan yang Anda buat akan tampil di sini."
        >
            <Button as-child class="h-11"
                ><Link :href="create()">Buat laporan pertama</Link></Button
            >
        </EmptyState>

        <ul v-else class="grid gap-3">
            <li v-for="report in reports.data" :key="report.id">
                <Link
                    :href="show.url(report.id)"
                    class="grid gap-2 rounded-xl border p-4 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span
                            class="text-sm font-medium text-muted-foreground"
                            >{{ report.ticket_number }}</span
                        >
                        <span class="flex items-center gap-2">
                            <span
                                v-if="report.is_overdue"
                                class="rounded-full bg-status-rejected-bg px-2.5 py-1 text-xs font-semibold text-status-rejected"
                                >Terlambat</span
                            >
                            <StatusBadge :status="report.status" />
                        </span>
                    </div>
                    <p class="font-semibold">{{ report.title }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ report.category ?? '-' }} ·
                        {{ report.building ?? '-' }}, {{ report.room ?? '-' }} ·
                        {{ formatDate(report.created_at) }}
                    </p>
                </Link>
            </li>
        </ul>

        <Pagination :links="reports.links" />
    </div>
</template>

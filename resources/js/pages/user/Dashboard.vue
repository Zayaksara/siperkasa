<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CircleCheck,
    CircleX,
    ClipboardList,
    Hourglass,
    Plus,
    Wrench,
} from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import OverdueBadge from '@/components/OverdueBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { create, index, show } from '@/routes/reports';
import { dashboard } from '@/routes/user';

defineProps<{
    stats: {
        waiting: number;
        processing: number;
        done: number;
        ended: number;
        recent: {
            id: number;
            ticket_number: string;
            title: string;
            status: string;
            is_overdue: boolean;
            created_at: string;
        }[];
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Beranda', href: dashboard() }] },
});
</script>

<template>
    <Head title="Beranda" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader title="Beranda" description="Ringkasan laporan Anda.">
            <Button as-child class="h-11">
                <Link :href="create()"
                    ><Plus class="size-4" /> Laporkan kerusakan</Link
                >
            </Button>
        </PageHeader>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard
                label="Menunggu validasi"
                :value="stats.waiting"
                :icon="Hourglass"
                accent="amber"
                hint="Admin sedang memeriksa"
                :href="index.url({ query: { status: 'Pending' } })"
            />
            <StatCard
                label="Sedang diproses"
                :value="stats.processing"
                :icon="Wrench"
                accent="sky"
                hint="Teknisi menangani"
                :href="
                    index.url({
                        query: { status: 'Assigned,In_Progress,On_Hold' },
                    })
                "
            />
            <StatCard
                label="Selesai"
                :value="stats.done"
                :icon="CircleCheck"
                accent="teal"
                hint="Perbaikan tuntas"
                :href="index.url({ query: { status: 'Closed' } })"
            />
            <StatCard
                label="Ditolak atau dibatalkan"
                :value="stats.ended"
                :icon="CircleX"
                accent="rose"
                hint="Lihat alasannya"
                :href="index.url({ query: { status: 'Rejected,Cancelled' } })"
            />
        </div>

        <section aria-labelledby="recent">
            <h2 id="recent" class="mb-2 font-semibold">Laporan terbaru</h2>
            <EmptyState
                v-if="stats.recent.length === 0"
                :icon="ClipboardList"
                accent="sky"
                title="Belum ada laporan"
                description="Temukan alat rusak? Laporkan lewat tombol di atas."
            />
            <ul v-else class="divide-y rounded-xl border">
                <li v-for="report in stats.recent" :key="report.id">
                    <Link
                        :href="show.url(report.id)"
                        class="flex min-h-11 items-center justify-between gap-3 p-3 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{
                                report.title
                            }}</span>
                            <span class="block text-sm text-muted-foreground">
                                {{ report.ticket_number }} ·
                                {{ formatDate(report.created_at) }}
                            </span>
                        </span>
                        <span class="flex shrink-0 flex-wrap justify-end gap-1">
                            <OverdueBadge v-if="report.is_overdue" />
                            <StatusBadge :status="report.status" />
                        </span>
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CircleCheck,
    CirclePause,
    ClipboardList,
    Inbox,
    TriangleAlert,
    Wrench,
} from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import HoldBadge from '@/components/HoldBadge.vue';
import OverdueBadge from '@/components/OverdueBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes/pic';
import { index, show } from '@/routes/pic/tasks';

defineProps<{
    stats: {
        new: number;
        in_progress: number;
        on_hold: number;
        overdue: number;
        done_this_month: number;
        upcoming: {
            id: number;
            ticket_number: string;
            title: string;
            status: string;
            is_overdue: boolean;
            sla_date: string | null;
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
        <PageHeader title="Beranda" description="Ringkasan tugas Anda." />

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <StatCard
                label="Tugas baru"
                :value="stats.new"
                :icon="Inbox"
                accent="sky"
                hint="Belum dimulai"
                :href="index.url({ query: { status: 'Assigned' } })"
            />
            <StatCard
                label="Sedang dikerjakan"
                :value="stats.in_progress"
                :icon="Wrench"
                accent="violet"
                :href="index.url({ query: { status: 'In_Progress' } })"
            />
            <StatCard
                label="Ditunda"
                :value="stats.on_hold"
                :icon="CirclePause"
                accent="amber"
                hint="Lanjutkan saat siap"
                :href="index.url({ query: { status: 'On_Hold' } })"
            />
            <StatCard
                label="Terlambat"
                :value="stats.overdue"
                :icon="TriangleAlert"
                accent="rose"
                hint="Lewat batas SLA"
                :href="index.url({ query: { overdue: 1 } })"
            />
            <StatCard
                label="Selesai bulan ini"
                :value="stats.done_this_month"
                :icon="CircleCheck"
                accent="teal"
                :href="index.url({ query: { tab: 'done' } })"
            />
        </div>

        <section aria-labelledby="upcoming">
            <h2 id="upcoming" class="mb-2 font-semibold">Batas SLA terdekat</h2>
            <EmptyState
                v-if="stats.upcoming.length === 0"
                :icon="ClipboardList"
                accent="sky"
                title="Tidak ada tugas aktif"
                description="Tugas baru dari admin akan muncul di sini."
            />
            <ul v-else class="divide-y rounded-xl border">
                <li v-for="task in stats.upcoming" :key="task.id">
                    <Link
                        :href="show.url(task.id)"
                        class="flex min-h-11 items-center justify-between gap-3 p-3 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{
                                task.title
                            }}</span>
                            <span class="block text-sm text-muted-foreground">
                                {{ task.ticket_number }} · Batas SLA
                                {{
                                    task.sla_date
                                        ? formatDateTime(task.sla_date)
                                        : '-'
                                }}
                            </span>
                        </span>
                        <span class="flex shrink-0 flex-wrap justify-end gap-1">
                            <OverdueBadge v-if="task.is_overdue" />
                            <HoldBadge
                                v-if="
                                    task.is_overdue && task.status === 'On_Hold'
                                "
                            />
                            <StatusBadge :status="task.status" />
                        </span>
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>

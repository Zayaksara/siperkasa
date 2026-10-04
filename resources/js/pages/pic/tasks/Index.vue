<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import EmptyState from "@/components/EmptyState.vue";
import HoldBadge from "@/components/HoldBadge.vue";
import OverdueBadge from "@/components/OverdueBadge.vue";
import PageHeader from "@/components/PageHeader.vue";
import Pagination from "@/components/Pagination.vue";
import StatusBadge from "@/components/StatusBadge.vue";
import { Button } from "@/components/ui/button";
import { formatDate, formatDateTime } from "@/lib/format";
import { index, show } from "@/routes/pic/tasks";

type Row = {
    id: number;
    ticket_number: string;
    title: string;
    status: string;
    status_label: string;
    admin_priority: string | null;
    admin_priority_label: string | null;
    is_overdue: boolean;
    sla_date: string | null;
    created_at: string;
    category: string | null;
    room: string | null;
    building: string | null;
    reporter: string | null;
};

defineProps<{
    tasks: {
        data: Row[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    tab: "active" | "done";
    counts: { active: number; done: number };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: "Tugas", href: index() }] },
});
</script>

<template>
    <Head title="Tugas Saya" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader
            title="Tugas Saya"
            description="Daftar laporan yang ditugaskan kepada Anda, urut dari SLA terdekat."
        />

        <nav class="flex gap-2" aria-label="Jenis tugas">
            <Button
                as-child
                class="h-11"
                :variant="tab === 'active' ? 'default' : 'outline'"
            >
                <Link
                    :href="index.url()"
                    :aria-current="tab === 'active' ? 'page' : undefined"
                    >Aktif ({{ counts.active }})</Link
                >
            </Button>
            <Button
                as-child
                class="h-11"
                :variant="tab === 'done' ? 'default' : 'outline'"
            >
                <Link
                    :href="index.url({ query: { tab: 'done' } })"
                    :aria-current="tab === 'done' ? 'page' : undefined"
                    >Selesai ({{ counts.done }})</Link
                >
            </Button>
        </nav>

        <EmptyState
            v-if="tasks.data.length === 0"
            :title="
                tab === 'active'
                    ? 'Tidak ada tugas aktif'
                    : 'Belum ada tugas selesai'
            "
            :description="
                tab === 'active'
                    ? 'Tugas baru dari admin akan muncul di sini.'
                    : 'Tugas yang sudah Anda selesaikan akan tampil di sini.'
            "
        />

        <ul v-else class="grid gap-3">
            <li v-for="task in tasks.data" :key="task.id">
                <Link
                    :href="show.url(task.id)"
                    class="grid gap-2 rounded-xl border p-4 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="task.is_overdue ? 'border-status-rejected' : ''"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span
                            class="text-sm font-medium text-muted-foreground"
                            >{{ task.ticket_number }}</span
                        >
                        <span class="flex flex-wrap items-center gap-2">
                            <OverdueBadge v-if="task.is_overdue" />
                            <HoldBadge v-if="task.status === 'On_Hold'" />
                            <StatusBadge
                                v-else
                                :status="task.status"
                                :label="task.status_label"
                            />
                        </span>
                    </div>
                    <p class="font-semibold break-words">{{ task.title }}</p>
                    <p class="text-sm break-words text-muted-foreground">
                        {{ task.category ?? "-" }} · {{ task.building ?? "-" }},
                        {{ task.room ?? "-" }}
                    </p>
                    <p
                        class="text-sm"
                        :class="
                            task.is_overdue
                                ? 'font-semibold text-status-rejected'
                                : 'text-muted-foreground'
                        "
                    >
                        SLA:
                        {{
                            task.sla_date ? formatDateTime(task.sla_date) : "-"
                        }}
                        · Dibuat {{ formatDate(task.created_at) }}
                    </p>
                </Link>
            </li>
        </ul>

        <Pagination :links="tasks.links" />
    </div>
</template>

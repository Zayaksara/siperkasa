<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CircleCheck,
    ClipboardList,
    Hourglass,
    Timer,
    TriangleAlert,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import ApexChart from '@/components/charts/ApexChart.vue';
import ChartLegendList from '@/components/charts/ChartLegendList.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatHours } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import { index as reportsIndex } from '@/routes/admin/reports';

const props = defineProps<{
    stats: {
        counts: Record<string, number>;
        total: number;
        overdue: number;
        waiting_validation: number;
        by_category: { id: number; name: string; total: number }[];
        by_building: { id: number; name: string; total: number }[];
        trend: {
            start: string;
            label: string;
            created: number;
            closed: number;
        }[];
        avg_completion_hours: number | null;
        avg_validation_hours: number | null;
        pic_load: { id: number; name: string; active_tasks: number }[];
    };
    range: { from: string; to: string };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Beranda', href: dashboard() }] },
});

const form = reactive({ from: props.range.from, to: props.range.to });

watch(
    () => props.range,
    (range) => Object.assign(form, range),
);

function apply() {
    router.get(
        dashboard.url(),
        { from: form.from, to: form.to },
        { preserveState: true, preserveScroll: true },
    );
}

function lastDays(days: number) {
    const to = new Date();
    const from = new Date(to.getTime() - (days - 1) * 86_400_000);
    const fmt = (d: Date) =>
        new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(
            d,
        );
    form.from = fmt(from);
    form.to = fmt(to);
    apply();
}

const link = (query: Record<string, string | number>) =>
    reportsIndex.url({
        query: { from: props.range.from, to: props.range.to, ...query },
    });

const visit = (href?: string) => {
    if (href) router.visit(href);
};

const barHeight = (rows: number) => Math.max(160, rows * 40 + 24);

const statuses: { key: string; label: string; color: string }[] = [
    { key: 'Pending', label: 'Menunggu validasi', color: '--status-pending' },
    { key: 'Assigned', label: 'Ditugaskan', color: '--status-assigned' },
    { key: 'In_Progress', label: 'Dikerjakan', color: '--status-in-progress' },
    { key: 'On_Hold', label: 'Ditunda', color: '--status-on-hold' },
    { key: 'Closed', label: 'Selesai', color: '--status-closed' },
    { key: 'Rejected', label: 'Ditolak', color: '--status-rejected' },
    { key: 'Cancelled', label: 'Dibatalkan', color: '--status-cancelled' },
];

const statusItems = computed(() =>
    statuses.map((s) => ({
        label: s.label,
        value: props.stats.counts[s.key] ?? 0,
        href: link({ status: s.key }),
        color: s.color,
    })),
);

type Hotspot = {
    id: string;
    title: string;
    description: string;
    color: string;
    unit: string;
    empty: string;
    items: { label: string; value: number; href: string; color: string }[];
};

const hotspots = computed<Hotspot[]>(() => [
    {
        id: 'category',
        title: 'Kategori dengan laporan terbanyak',
        description:
            'Kategori alat yang paling sering rusak. Klik untuk melihat laporannya.',
        color: '--accent-teal',
        unit: 'laporan',
        empty: 'Belum ada laporan pada rentang ini.',
        items: props.stats.by_category.map((c) => ({
            label: c.name,
            value: c.total,
            href: link({ category_id: c.id }),
            color: '--accent-teal',
        })),
    },
    {
        id: 'building',
        title: 'Gedung dengan laporan terbanyak',
        description:
            'Gedung yang perlu perhatian lebih. Klik untuk melihat laporannya.',
        color: '--accent-sky',
        unit: 'laporan',
        empty: 'Belum ada laporan pada rentang ini.',
        items: props.stats.by_building.map((b) => ({
            label: b.name,
            value: b.total,
            href: link({ building_id: b.id }),
            color: '--accent-sky',
        })),
    },
    {
        id: 'load',
        title: 'Beban kerja PIC',
        description:
            'Tugas aktif tiap PIC. Pilih PIC dengan beban rendah saat menugaskan.',
        color: '--accent-violet',
        unit: 'tugas aktif',
        empty: 'Belum ada PIC aktif.',
        items: props.stats.pic_load.map((p) => ({
            label: p.name,
            value: p.active_tasks,
            href: reportsIndex.url({ query: { pic_id: p.id } }),
            color: '--accent-violet',
        })),
    },
]);

const statusHasData = computed(() =>
    statusItems.value.some((s) => s.value > 0),
);
const trendHasData = computed(() =>
    props.stats.trend.some((w) => w.created > 0 || w.closed > 0),
);

const trendSeries = computed(() => [
    { name: 'Laporan masuk', data: props.stats.trend.map((w) => w.created) },
    { name: 'Laporan selesai', data: props.stats.trend.map((w) => w.closed) },
]);

function addDays(date: string, days: number) {
    const d = new Date(`${date}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
}

function selectWeek(index: number, seriesIndex: number) {
    if (seriesIndex !== 0) return; // closed_at is not a list filter
    const week = props.stats.trend[index];
    if (!week) return;
    const query: Record<string, string> = {
        from: week.start,
        to: addDays(week.start, 6),
    };
    router.visit(reportsIndex.url({ query }));
}
</script>

<template>
    <Head title="Beranda" />

    <div class="flex min-w-0 flex-col gap-6 p-4">
        <PageHeader
            title="Beranda"
            description="Ringkasan laporan pada rentang tanggal yang dipilih."
        />

        <form
            class="flex flex-wrap items-end gap-3 rounded-xl border p-4"
            @submit.prevent="apply"
        >
            <div class="grid gap-1">
                <Label for="d-from">Dari</Label>
                <Input
                    id="d-from"
                    v-model="form.from"
                    type="date"
                    class="h-11"
                    required
                />
            </div>
            <div class="grid gap-1">
                <Label for="d-to">Sampai</Label>
                <Input
                    id="d-to"
                    v-model="form.to"
                    type="date"
                    class="h-11"
                    required
                />
            </div>
            <Button type="submit" class="h-11">Terapkan</Button>
            <Button
                type="button"
                variant="outline"
                class="h-11"
                @click="lastDays(7)"
                >7 hari</Button
            >
            <Button
                type="button"
                variant="outline"
                class="h-11"
                @click="lastDays(30)"
                >30 hari</Button
            >
            <Button
                type="button"
                variant="outline"
                class="h-11"
                @click="lastDays(90)"
                >90 hari</Button
            >
        </form>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <StatCard
                label="Menunggu validasi"
                :value="stats.waiting_validation"
                :icon="Hourglass"
                accent="amber"
                hint="Antrean yang perlu diperiksa"
                :href="link({ status: 'Pending' })"
            />
            <StatCard
                label="Terlambat saat ini"
                :value="stats.overdue"
                :icon="TriangleAlert"
                accent="rose"
                hint="Lewat batas SLA"
                :href="reportsIndex.url({ query: { overdue: 1 } })"
            />
            <StatCard
                label="Total laporan"
                :value="stats.total"
                :icon="ClipboardList"
                accent="sky"
                hint="Pada rentang tanggal"
                :href="link({})"
            />
            <StatCard
                label="Rata-rata waktu validasi"
                :value="formatHours(stats.avg_validation_hours)"
                :icon="Timer"
                accent="violet"
                hint="Sejak dilaporkan sampai divalidasi"
            />
            <StatCard
                label="Rata-rata waktu penyelesaian"
                :value="formatHours(stats.avg_completion_hours)"
                :icon="CircleCheck"
                accent="teal"
                hint="Sejak dilaporkan sampai selesai"
            />
        </div>

        <section
            class="min-w-0 rounded-xl border p-4"
            aria-labelledby="by-status"
        >
            <h2 id="by-status" class="font-semibold">Laporan menurut status</h2>
            <p class="mb-3 text-sm text-muted-foreground">
                Lihat di tahap mana laporan menumpuk. Klik batang atau daftar
                untuk membuka laporannya.
            </p>
            <EmptyState
                v-if="!statusHasData"
                :icon="ClipboardList"
                accent="sky"
                title="Belum ada laporan"
                description="Tidak ada laporan pada rentang ini."
            />
            <figure v-else class="grid min-w-0 gap-3">
                <figcaption class="sr-only">
                    Jumlah laporan menurut status
                </figcaption>
                <ApexChart
                    type="bar"
                    horizontal
                    distributed
                    :height="barHeight(statusItems.length)"
                    :categories="statusItems.map((s) => s.label)"
                    :series="[
                        {
                            name: 'Laporan',
                            data: statusItems.map((s) => s.value),
                        },
                    ]"
                    :colors="statusItems.map((s) => s.color)"
                    @select="(i) => visit(statusItems[i]?.href)"
                />
                <table class="sr-only">
                    <caption>
                        Jumlah laporan menurut status
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Status</th>
                            <th scope="col">Jumlah laporan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in statusItems" :key="s.label">
                            <td>{{ s.label }}</td>
                            <td>{{ s.value }}</td>
                        </tr>
                    </tbody>
                </table>
                <ChartLegendList :items="statusItems" unit="laporan" />
            </figure>
        </section>

        <section class="min-w-0 rounded-xl border p-4" aria-labelledby="trend">
            <h2 id="trend" class="font-semibold">
                Laporan masuk dan selesai, 12 minggu terakhir
            </h2>
            <p class="mb-3 text-sm text-muted-foreground">
                Bandingkan laporan baru dengan yang selesai. Jika garis masuk di
                atas garis selesai, antrean bertambah. Klik titik laporan masuk
                untuk membuka laporan minggu itu.
            </p>
            <EmptyState
                v-if="!trendHasData"
                :icon="ClipboardList"
                accent="sky"
                title="Belum ada data tren"
                description="Belum ada laporan dalam 12 minggu terakhir."
            />
            <figure v-else class="grid min-w-0 gap-3">
                <figcaption class="sr-only">
                    Laporan masuk dan selesai per minggu
                </figcaption>
                <ApexChart
                    type="line"
                    :height="280"
                    :categories="stats.trend.map((w) => w.label)"
                    :series="trendSeries"
                    :colors="['--accent-sky', '--chart-1']"
                    x-title="Awal minggu (tanggal/bulan)"
                    @select="selectWeek"
                />
                <table class="sr-only">
                    <caption>
                        Laporan masuk dan selesai per minggu
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Minggu mulai</th>
                            <th scope="col">Laporan masuk</th>
                            <th scope="col">Laporan selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="week in stats.trend" :key="week.start">
                            <td>{{ week.start }}</td>
                            <td>{{ week.created }}</td>
                            <td>{{ week.closed }}</td>
                        </tr>
                    </tbody>
                </table>
            </figure>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section
                v-for="spot in hotspots"
                :key="spot.id"
                class="min-w-0 rounded-xl border p-4"
                :class="spot.id === 'load' ? 'lg:col-span-2' : ''"
                :aria-labelledby="`h-${spot.id}`"
            >
                <h2 :id="`h-${spot.id}`" class="font-semibold">
                    {{ spot.title }}
                </h2>
                <p class="mb-3 text-sm text-muted-foreground">
                    {{ spot.description }}
                </p>
                <EmptyState
                    v-if="
                        spot.items.length === 0 ||
                        spot.items.every((i) => i.value === 0)
                    "
                    :icon="ClipboardList"
                    accent="sky"
                    :title="spot.empty"
                />
                <figure v-else class="grid min-w-0 gap-3">
                    <figcaption class="sr-only">{{ spot.title }}</figcaption>
                    <ApexChart
                        type="bar"
                        horizontal
                        :unit="spot.unit"
                        :height="barHeight(spot.items.length)"
                        :categories="spot.items.map((i) => i.label)"
                        :series="[
                            {
                                name:
                                    spot.unit === 'laporan'
                                        ? 'Laporan'
                                        : 'Tugas aktif',
                                data: spot.items.map((i) => i.value),
                            },
                        ]"
                        :colors="[spot.color]"
                        @select="(i) => visit(spot.items[i]?.href)"
                    />
                    <table class="sr-only">
                        <caption>
                            {{
                                spot.title
                            }}
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Nama</th>
                                <th scope="col">Jumlah {{ spot.unit }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in spot.items" :key="item.label">
                                <td>{{ item.label }}</td>
                                <td>{{ item.value }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <ChartLegendList :items="spot.items" :unit="spot.unit" />
                </figure>
            </section>
        </div>
    </div>
</template>

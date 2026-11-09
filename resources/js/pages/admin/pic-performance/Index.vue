<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    CircleCheck,
    ClipboardList,
    Gauge,
    Hourglass,
    TriangleAlert,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import ApexChart from '@/components/charts/ApexChart.vue';
import ChartLegendList from '@/components/charts/ChartLegendList.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateRange } from '@/lib/format';
import { motion, useMotion } from '@/lib/motion';
import { dashboard, picPerformance } from '@/routes/admin';
import { index as reportsIndex } from '@/routes/admin/reports';

type PicRow = {
    id: number;
    name: string;
    trashed: boolean;
    active: boolean;
    total: number;
    on_time: number;
    late_closed: number;
    late_open: number;
    on_track: number;
    on_time_rate: number | null;
    avg_late_hours: number | null;
};

const props = defineProps<{
    performance: {
        summary: {
            total: number;
            on_time: number;
            late_closed: number;
            late_open: number;
            on_track: number;
            on_time_rate: number | null;
        };
        pics: PicRow[];
    };
    range: { from: string; to: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: dashboard() },
            { title: 'Kinerja PIC', href: picPerformance() },
        ],
    },
});

const form = reactive({ from: props.range.from, to: props.range.to });

// Resync only when the applied range really changes (back/forward); a rejected
// request keeps what the user typed next to its error.
watch(
    () => `${props.range.from}|${props.range.to}`,
    () => Object.assign(form, props.range),
);

const errors = computed(
    () => usePage().props.errors as Record<string, string | undefined>,
);

function apply() {
    router.get(
        picPerformance.url(),
        { from: form.from, to: form.to },
        { preserveState: true, preserveScroll: true },
    );
}

const nf = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });
const percent = (v: number | null) => (v === null ? '—' : `${nf.format(v)}%`);
const lateHours = (v: number | null) => (v === null ? '—' : nf.format(v));

const overdueLink = (picId: number) =>
    reportsIndex.url({ query: { pic_id: picId, overdue: 1 } });

const summary = computed(() => props.performance.summary);
const pics = computed(() => props.performance.pics);

// Colours: green for done on time and still running, amber for finished late,
// the app's overdue red (same token as OverdueBadge) for still late.
const series = [
    {
        key: 'on_time',
        name: 'Tepat waktu',
        color: '--chart-1',
    },
    {
        key: 'late_closed',
        name: 'Terlambat selesai',
        color: '--accent-amber',
    },
    {
        key: 'late_open',
        name: 'Masih terlambat',
        color: '--status-rejected',
    },
    { key: 'on_track', name: 'Berjalan', color: '--chart-3' },
] as const;

const legendItems = computed(() =>
    series.map((s) => ({
        label: s.name,
        value: summary.value[s.key],
        color: s.color,
    })),
);

const chartSeries = computed(() =>
    series.map((s) => ({
        name: s.name,
        data: pics.value.map((p) => p[s.key]),
    })),
);

const chartHeight = computed(() => Math.max(180, pics.value.length * 44 + 80));

function selectBar(index: number, seriesIndex: number) {
    const pic = pics.value[index];
    if (pic && series[seriesIndex]?.key === 'late_open' && pic.late_open > 0)
        router.visit(overdueLink(pic.id));
}

type SortKey =
    | 'name'
    | 'total'
    | 'on_time'
    | 'late_closed'
    | 'late_open'
    | 'on_track'
    | 'on_time_rate'
    | 'avg_late_hours';

const columns: { key: SortKey; label: string; numeric: boolean }[] = [
    { key: 'name', label: 'PIC', numeric: false },
    { key: 'total', label: 'Total', numeric: true },
    { key: 'on_time', label: 'Tepat waktu', numeric: true },
    { key: 'late_closed', label: 'Terlambat selesai', numeric: true },
    { key: 'late_open', label: 'Masih terlambat', numeric: true },
    { key: 'on_track', label: 'Berjalan', numeric: true },
    { key: 'on_time_rate', label: '% tepat waktu', numeric: true },
    { key: 'avg_late_hours', label: 'Rata telat (jam)', numeric: true },
];

// Default order is the server's (worst rate first). A header cycles
// ascending, descending, back to default.
const sort = ref<{ key: SortKey; dir: 'asc' | 'desc' } | null>(null);

function toggleSort(key: SortKey) {
    const cur = sort.value;
    if (!cur || cur.key !== key) sort.value = { key, dir: 'asc' };
    else if (cur.dir === 'asc') sort.value = { key, dir: 'desc' };
    else sort.value = null;
}

const sortedPics = computed(() => {
    const s = sort.value;
    if (!s) return pics.value;
    const factor = s.dir === 'asc' ? 1 : -1;
    return [...pics.value].sort((a, b) => {
        const x = a[s.key];
        const y = b[s.key];
        if (x === null && y === null) return 0;
        if (x === null) return 1; // missing values always last
        if (y === null) return -1;
        if (typeof x === 'string' && typeof y === 'string')
            return factor * x.localeCompare(y, 'id');
        return factor * ((x as number) - (y as number));
    });
});

const ariaSort = (key: SortKey) =>
    sort.value?.key === key
        ? sort.value.dir === 'asc'
            ? 'ascending'
            : 'descending'
        : 'none';

// Entrance motion: first mount only.
const root = ref<HTMLElement | null>(null);
let entered = false;
useMotion(
    root,
    ({ gsap }) => {
        if (entered) return;
        entered = true;
        gsap.from('[data-reveal]', {
            autoAlpha: 0,
            y: motion.rise,
            duration: 0.5,
            stagger: 0.06,
            clearProps: 'transform,opacity,visibility',
        });
        gsap.from('[data-row]', {
            autoAlpha: 0,
            y: 8,
            duration: 0.4,
            stagger: 0.03,
            delay: 0.3,
            clearProps: 'transform,opacity,visibility',
        });
    },
    { hideUntilReady: true },
);
</script>

<template>
    <Head title="Kinerja PIC" />

    <div ref="root" class="flex min-w-0 flex-col gap-6 p-4">
        <PageHeader
            title="Kinerja PIC"
            description="Ketepatan PIC menyelesaikan tugas sesuai batas SLA."
        />

        <form
            data-reveal
            class="flex flex-wrap items-end gap-3 rounded-xl border p-4"
            @submit.prevent="apply"
        >
            <div class="grid gap-1">
                <Label for="p-from">Dari</Label>
                <Input
                    id="p-from"
                    v-model="form.from"
                    type="date"
                    class="h-11"
                    required
                    :aria-invalid="Boolean(errors.from)"
                    :aria-describedby="errors.from ? 'p-from-error' : undefined"
                />
                <InputError id="p-from-error" :message="errors.from" />
            </div>
            <div class="grid gap-1">
                <Label for="p-to">Sampai</Label>
                <Input
                    id="p-to"
                    v-model="form.to"
                    type="date"
                    class="h-11"
                    required
                    :aria-invalid="Boolean(errors.to)"
                    :aria-describedby="errors.to ? 'p-to-error' : undefined"
                />
                <InputError id="p-to-error" :message="errors.to" />
            </div>
            <Button type="submit" class="h-11">Terapkan</Button>
            <p class="basis-full text-sm text-muted-foreground">
                Periode: {{ formatDateRange(range.from, range.to) }}. Rentang
                memakai tanggal validasi. Status terlambat dinilai per hari ini.
                Laporan dibatalkan/ditolak tidak dihitung.
            </p>
        </form>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard
                data-reveal
                label="Total tugas"
                :value="summary.total"
                :icon="ClipboardList"
                accent="sky"
                hint="Tugas ber-SLA pada rentang"
            />
            <StatCard
                data-reveal
                label="Tepat waktu"
                :value="percent(summary.on_time_rate)"
                :icon="CircleCheck"
                accent="teal"
                hint="Dari tugas yang sudah dinilai"
            />
            <StatCard
                data-reveal
                label="Terlambat selesai"
                :value="summary.late_closed"
                :icon="Hourglass"
                accent="amber"
                hint="Selesai setelah batas SLA"
            />
            <StatCard
                data-reveal
                label="Masih terlambat"
                :value="summary.late_open"
                :icon="TriangleAlert"
                accent="rose"
                hint="Belum selesai, lewat batas SLA"
            />
        </div>

        <EmptyState
            v-if="pics.length === 0"
            data-reveal
            :icon="Gauge"
            accent="teal"
            title="Belum ada data kinerja"
            description="Belum ada tugas ber-SLA yang divalidasi pada rentang ini. Ubah rentang tanggal untuk melihat PIC lain."
        />

        <template v-else>
            <section
                data-reveal
                class="min-w-0 rounded-xl border p-4"
                aria-labelledby="by-pic"
            >
                <h2 id="by-pic" class="font-semibold">Tugas per PIC</h2>
                <p class="mb-3 text-sm text-muted-foreground">
                    PIC dengan ketepatan terendah ada di atas. Klik bagian merah
                    untuk membuka laporan yang masih terlambat.
                </p>
                <figure class="grid min-w-0 gap-3">
                    <figcaption class="sr-only">
                        Jumlah tugas per PIC menurut ketepatan waktu
                    </figcaption>
                    <ApexChart
                        type="bar"
                        horizontal
                        stacked
                        unit="tugas"
                        :height="chartHeight"
                        :categories="pics.map((p) => p.name)"
                        :series="chartSeries"
                        :colors="series.map((s) => s.color)"
                        @select="selectBar"
                    />
                    <ChartLegendList :items="legendItems" unit="tugas" />
                </figure>
            </section>

            <section
                data-reveal
                class="min-w-0 rounded-xl border p-4"
                aria-labelledby="pic-table"
            >
                <h2 id="pic-table" class="font-semibold">Rincian per PIC</h2>
                <p class="mb-3 text-sm text-muted-foreground">
                    Klik judul kolom untuk mengurutkan. Klik jumlah terlambat
                    untuk membuka laporannya.
                </p>
                <div class="max-w-full overflow-x-auto">
                    <table class="w-full min-w-[44rem] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th
                                    v-for="col in columns"
                                    :key="col.key"
                                    scope="col"
                                    :aria-sort="ariaSort(col.key)"
                                    class="font-medium"
                                    :class="col.numeric ? 'text-right' : ''"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex min-h-11 items-center gap-1 px-2 whitespace-nowrap hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        :class="
                                            col.numeric
                                                ? 'flex-row-reverse'
                                                : ''
                                        "
                                        @click="toggleSort(col.key)"
                                    >
                                        {{ col.label }}
                                        <ArrowUp
                                            v-if="
                                                ariaSort(col.key) ===
                                                'ascending'
                                            "
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        <ArrowDown
                                            v-else-if="
                                                ariaSort(col.key) ===
                                                'descending'
                                            "
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        <ArrowUpDown
                                            v-else
                                            class="size-3.5 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="pic in sortedPics"
                                :key="pic.id"
                                data-row
                                class="border-b last:border-0"
                            >
                                <th
                                    scope="row"
                                    class="px-2 py-2 text-left font-medium"
                                >
                                    <span
                                        class="inline-flex flex-wrap items-center gap-2"
                                    >
                                        {{ pic.name }}
                                        <Badge
                                            v-if="pic.trashed"
                                            variant="outline"
                                            >terhapus</Badge
                                        >
                                        <Badge
                                            v-else-if="!pic.active"
                                            variant="outline"
                                            >nonaktif</Badge
                                        >
                                    </span>
                                </th>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {{ pic.total }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {{ pic.on_time }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {{ pic.late_closed }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    <Link
                                        v-if="pic.late_open > 0"
                                        :href="overdueLink(pic.id)"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-end font-semibold text-status-rejected underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >{{ pic.late_open }}</Link
                                    >
                                    <template v-else>0</template>
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {{ pic.on_track }}
                                </td>
                                <td
                                    class="px-2 py-2 text-right font-semibold tabular-nums"
                                >
                                    {{ percent(pic.on_time_rate) }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {{ lateHours(pic.avg_late_hours) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
</template>

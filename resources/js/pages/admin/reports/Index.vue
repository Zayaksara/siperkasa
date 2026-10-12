<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { FileSpreadsheet, FileText, Loader2 } from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import OverdueBadge from '@/components/OverdueBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDate, formatDateTime } from '@/lib/format';
import { index, show } from '@/routes/admin/reports';
import { excel, pdf } from '@/routes/admin/reports/export';

// Whole row opens the detail; links, buttons, inputs and text selection keep their own behaviour.
function openRow(event: MouseEvent, id: number) {
    const target = event.target as HTMLElement;
    if (target.closest('a, button, input, select, textarea, label')) return;
    if (window.getSelection()?.toString()) return;

    router.visit(show.url(id));
}

type Row = {
    id: number;
    ticket_number: string;
    title: string;
    status: string;
    status_label: string;
    user_priority: string;
    user_priority_label: string;
    admin_priority: string | null;
    admin_priority_label: string | null;
    is_overdue: boolean;
    sla_date: string | null;
    created_at: string;
    category: string | null;
    room: string | null;
    building: string | null;
    reporter: string | null;
    pic: string | null;
};

type Option = { id: number; name: string };
type Choice = { value: string; label: string };

const props = defineProps<{
    reports: {
        data: Row[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: Record<string, string | number | boolean | undefined>;
    options: {
        categories: Option[];
        buildings: Option[];
        faculties: string[];
        pics: Option[];
        statuses: Choice[];
        priorities: Choice[];
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Laporan', href: index() }] },
});

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const f = reactive({
    q: String(props.filters.q ?? ''),
    status: String(props.filters.status ?? ''),
    category_id: String(props.filters.category_id ?? ''),
    building_id: String(props.filters.building_id ?? ''),
    faculty: String(props.filters.faculty ?? ''),
    pic_id: String(props.filters.pic_id ?? ''),
    priority: String(props.filters.priority ?? ''),
    from: String(props.filters.from ?? ''),
    to: String(props.filters.to ?? ''),
    overdue:
        props.filters.overdue === true ||
        props.filters.overdue === '1' ||
        props.filters.overdue === 1,
});

function syncFromProps() {
    Object.assign(f, {
        q: String(props.filters.q ?? ''),
        status: String(props.filters.status ?? ''),
        category_id: String(props.filters.category_id ?? ''),
        building_id: String(props.filters.building_id ?? ''),
        faculty: String(props.filters.faculty ?? ''),
        pic_id: String(props.filters.pic_id ?? ''),
        priority: String(props.filters.priority ?? ''),
        from: String(props.filters.from ?? ''),
        to: String(props.filters.to ?? ''),
        overdue:
            props.filters.overdue === true ||
            props.filters.overdue === '1' ||
            props.filters.overdue === 1,
    });
}

// Back/forward navigation changes props.filters without remounting the page.
watch(() => props.filters, syncFromProps);

const hasFilters = computed(() =>
    Object.values(props.filters).some(
        (value) => value !== undefined && value !== '' && value !== false,
    ),
);

// Same filters as the list; overdue is sent as 1 so the boolean rule accepts it.
const exportQuery = computed(() =>
    Object.fromEntries(
        Object.entries(props.filters)
            .filter(
                ([, value]) =>
                    value !== '' && value !== undefined && value !== false,
            )
            .map(([key, value]) => [key, value === true ? 1 : value]),
    ),
);
const exportError = computed(() => errors.value?.export);

// A file download keeps the page, so show "Menyiapkan…" for 5 s to stop double clicks.
const preparing = ref<'excel' | 'pdf' | null>(null);
let preparingTimer: ReturnType<typeof setTimeout> | undefined;

function startExport(event: MouseEvent, kind: 'excel' | 'pdf') {
    if (preparing.value) {
        event.preventDefault();
        return;
    }
    preparing.value = kind;
    preparingTimer = setTimeout(() => (preparing.value = null), 5000);
}

onBeforeUnmount(() => clearTimeout(preparingTimer));

function apply() {
    const query = Object.fromEntries(
        Object.entries(f)
            .filter(([, value]) => value !== '' && value !== false)
            .map(([key, value]) => [key, value === true ? 1 : value]),
    );
    router.get(index.url(), query, { preserveState: true, replace: true });
}

function reset() {
    Object.assign(f, {
        q: '',
        status: '',
        category_id: '',
        building_id: '',
        faculty: '',
        pic_id: '',
        priority: '',
        from: '',
        to: '',
        overdue: false,
    });
    apply();
}
</script>

<template>
    <Head title="Laporan" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader
            title="Laporan Kerusakan"
            description="Validasi laporan baru, pantau SLA, dan pindahkan PIC bila perlu."
        >
            <Button
                as-child
                variant="outline"
                class="h-11 min-w-44"
                :aria-disabled="preparing !== null"
                :class="{ 'pointer-events-none opacity-60': preparing }"
            >
                <a
                    :href="excel.url({ query: exportQuery })"
                    :aria-disabled="preparing !== null"
                    @click="startExport($event, 'excel')"
                >
                    <Loader2
                        v-if="preparing === 'excel'"
                        class="size-4 animate-spin motion-reduce:animate-none"
                    />
                    <FileSpreadsheet v-else class="size-4" />
                    {{ preparing === 'excel' ? 'Menyiapkan…' : 'Export Excel' }}
                </a>
            </Button>
            <Button
                as-child
                variant="outline"
                class="h-11 min-w-44"
                :aria-disabled="preparing !== null"
                :class="{ 'pointer-events-none opacity-60': preparing }"
            >
                <a
                    :href="pdf.url({ query: exportQuery })"
                    :aria-disabled="preparing !== null"
                    @click="startExport($event, 'pdf')"
                >
                    <Loader2
                        v-if="preparing === 'pdf'"
                        class="size-4 animate-spin motion-reduce:animate-none"
                    />
                    <FileText v-else class="size-4" />
                    {{ preparing === 'pdf' ? 'Menyiapkan…' : 'Export PDF' }}
                </a>
            </Button>
        </PageHeader>
        <span class="sr-only" aria-live="polite">{{
            preparing ? 'Menyiapkan file export' : ''
        }}</span>
        <p
            v-if="exportError"
            class="rounded-md border border-destructive/40 bg-status-rejected-bg p-3 text-sm text-status-rejected"
            role="alert"
        >
            {{ exportError }}
        </p>

        <form
            class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2 lg:grid-cols-4"
            @submit.prevent="apply"
        >
            <div class="grid gap-1 sm:col-span-2">
                <Label for="f-q">Cari</Label>
                <Input
                    id="f-q"
                    v-model="f.q"
                    class="h-11"
                    placeholder="Nomor tiket atau judul"
                />
            </div>
            <div class="grid gap-1">
                <Label for="f-status">Status</Label>
                <NativeSelect
                    id="f-status"
                    v-model="f.status"
                    :aria-invalid="!!errors.status"
                >
                    <option value="">Semua status</option>
                    <option
                        v-for="s in options.statuses"
                        :key="s.value"
                        :value="s.value"
                    >
                        {{ s.label }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.status" />
            </div>
            <div class="grid gap-1">
                <Label for="f-priority">Prioritas</Label>
                <NativeSelect
                    id="f-priority"
                    v-model="f.priority"
                    :aria-invalid="!!errors.priority"
                >
                    <option value="">Semua prioritas</option>
                    <option
                        v-for="p in options.priorities"
                        :key="p.value"
                        :value="p.value"
                    >
                        {{ p.label }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.priority" />
            </div>
            <div class="grid gap-1">
                <Label for="f-category">Kategori</Label>
                <NativeSelect id="f-category" v-model="f.category_id">
                    <option value="">Semua kategori</option>
                    <option
                        v-for="c in options.categories"
                        :key="c.id"
                        :value="String(c.id)"
                    >
                        {{ c.name }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.category_id" />
            </div>
            <div class="grid gap-1">
                <Label for="f-building">Gedung</Label>
                <NativeSelect id="f-building" v-model="f.building_id">
                    <option value="">Semua gedung</option>
                    <option
                        v-for="b in options.buildings"
                        :key="b.id"
                        :value="String(b.id)"
                    >
                        {{ b.name }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.building_id" />
            </div>
            <div class="grid gap-1">
                <Label for="f-faculty">Fakultas pelapor</Label>
                <NativeSelect id="f-faculty" v-model="f.faculty">
                    <option value="">Semua fakultas</option>
                    <option
                        v-for="fac in options.faculties"
                        :key="fac"
                        :value="fac"
                    >
                        {{ fac }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.faculty" />
            </div>
            <div class="grid gap-1">
                <Label for="f-pic">PIC</Label>
                <NativeSelect id="f-pic" v-model="f.pic_id">
                    <option value="">Semua PIC</option>
                    <option
                        v-for="p in options.pics"
                        :key="p.id"
                        :value="String(p.id)"
                    >
                        {{ p.name }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.pic_id" />
            </div>
            <div class="grid gap-1">
                <Label for="f-from">Dari tanggal</Label>
                <Input
                    id="f-from"
                    v-model="f.from"
                    type="date"
                    class="h-11"
                    :aria-invalid="!!errors.from"
                />
                <InputError :message="errors.from" />
            </div>
            <div class="grid gap-1">
                <Label for="f-to">Sampai tanggal</Label>
                <Input
                    id="f-to"
                    v-model="f.to"
                    type="date"
                    class="h-11"
                    :min="f.from || undefined"
                    :aria-invalid="!!errors.to"
                />
                <InputError :message="errors.to" />
            </div>
            <Label for="f-overdue" class="flex min-h-11 items-center gap-3">
                <Checkbox
                    id="f-overdue"
                    :model-value="f.overdue"
                    class="size-5"
                    @update:model-value="f.overdue = $event === true"
                />
                <span>Hanya yang terlambat</span>
            </Label>
            <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-4">
                <Button type="submit" class="h-11">Terapkan filter</Button>
                <Button
                    type="button"
                    variant="outline"
                    class="h-11"
                    @click="reset"
                    >Atur ulang</Button
                >
            </div>
        </form>

        <EmptyState
            v-if="reports.data.length === 0 && hasFilters"
            title="Tidak ada laporan yang cocok"
            description="Ubah atau hapus filter untuk melihat laporan lain."
        >
            <Button variant="outline" class="h-11" @click="reset"
                >Hapus filter</Button
            >
        </EmptyState>

        <EmptyState
            v-else-if="reports.data.length === 0"
            title="Belum ada laporan"
            description="Laporan yang dibuat pengguna akan tampil di sini."
        />

        <template v-else>
            <ul class="grid gap-3 lg:hidden">
                <li v-for="r in reports.data" :key="r.id">
                    <Link
                        :href="show.url(r.id)"
                        class="grid gap-2 rounded-xl border p-4 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >{{ r.ticket_number }}</span
                            >
                            <span class="flex flex-wrap items-center gap-2">
                                <OverdueBadge v-if="r.is_overdue" />
                                <StatusBadge
                                    :status="r.status"
                                    :label="r.status_label"
                                />
                            </span>
                        </div>
                        <p class="font-semibold">{{ r.title }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ r.reporter ?? '-' }} · {{ r.category ?? '-' }} ·
                            {{ r.building ?? '-' }}, {{ r.room ?? '-' }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Urgensi: {{ r.user_priority_label }} · Prioritas:
                            {{ r.admin_priority_label ?? '-' }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            PIC: {{ r.pic ?? '-' }} · SLA:
                            {{ r.sla_date ? formatDateTime(r.sla_date) : '-' }}
                        </p>
                    </Link>
                </li>
            </ul>

            <div class="hidden overflow-x-auto rounded-xl border lg:block">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">
                        Daftar laporan kerusakan
                    </caption>
                    <thead class="bg-muted text-muted-foreground">
                        <tr>
                            <th scope="col" class="p-3 font-medium">Tiket</th>
                            <th scope="col" class="p-3 font-medium">Judul</th>
                            <th scope="col" class="p-3 font-medium">
                                Kategori / lokasi
                            </th>
                            <th scope="col" class="p-3 font-medium">Urgensi</th>
                            <th scope="col" class="p-3 font-medium">
                                Prioritas
                            </th>
                            <th scope="col" class="p-3 font-medium">Status</th>
                            <th scope="col" class="p-3 font-medium">PIC</th>
                            <th scope="col" class="p-3 font-medium">SLA</th>
                            <th scope="col" class="p-3 font-medium">Dibuat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="r in reports.data"
                            :key="r.id"
                            class="cursor-pointer hover:bg-accent"
                            data-test="report-row"
                            @click="openRow($event, r.id)"
                        >
                            <td class="p-3 whitespace-nowrap">
                                <Link
                                    :href="show.url(r.id)"
                                    class="font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >{{ r.ticket_number }}</Link
                                >
                            </td>
                            <td class="p-3">
                                {{ r.title
                                }}<span class="block text-muted-foreground">{{
                                    r.reporter ?? '-'
                                }}</span>
                            </td>
                            <td class="p-3">
                                {{ r.category ?? '-'
                                }}<span class="block text-muted-foreground"
                                    >{{ r.building ?? '-' }},
                                    {{ r.room ?? '-' }}</span
                                >
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                {{ r.user_priority_label }}
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                {{ r.admin_priority_label ?? '-' }}
                            </td>
                            <td class="p-3">
                                <span class="flex flex-wrap items-center gap-2">
                                    <StatusBadge
                                        :status="r.status"
                                        :label="r.status_label"
                                    />
                                </span>
                            </td>
                            <td class="p-3">{{ r.pic ?? '-' }}</td>
                            <td class="p-3 whitespace-nowrap">
                                {{
                                    r.sla_date
                                        ? formatDateTime(r.sla_date)
                                        : '-'
                                }}<OverdueBadge
                                    v-if="r.is_overdue"
                                    class="mt-1 block w-fit"
                                />
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                {{ formatDate(r.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <Pagination :links="reports.links" />
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import Timeline from '@/components/Timeline.vue';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime, formatRupiah } from '@/lib/format';
import { cancel, edit, index } from '@/routes/reports';

type Photo = { id: number; url: string };

const props = defineProps<{
    report: {
        id: number;
        ticket_number: string;
        title: string;
        description: string;
        status: string;
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
        pic: string | null;
        asset: { asset_code: string; name: string } | null;
        rejection_reason: string | null;
        hold_reason: string | null;
        repair_action: string | null;
        completed_on: string | null;
        repair_cost: string | null;
        vendor_name: string | null;
        damage_photos: Photo[];
        completion_photos: Photo[];
        timeline: {
            id: number;
            to_status: string;
            to_label: string;
            actor: string | null;
            note: string | null;
            created_at: string;
        }[];
    };
    can: { update: boolean; cancel: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Laporan Saya', href: index() }] },
});

const confirmCancel = ref(false);

function doCancel() {
    router.post(
        cancel.url(props.report.id),
        {},
        { preserveScroll: true, onFinish: () => (confirmCancel.value = false) },
    );
}

const details: [string, string][] = [
    ['Kategori', props.report.category ?? '-'],
    ['Lokasi', `${props.report.building ?? '-'}, ${props.report.room ?? '-'}`],
    [
        'Aset',
        props.report.asset
            ? `${props.report.asset.name} (${props.report.asset.asset_code})`
            : 'Tidak dipilih',
    ],
    ['Urgensi Anda', props.report.user_priority_label],
    [
        'Prioritas admin',
        props.report.admin_priority_label ?? 'Belum ditentukan',
    ],
    [
        'Target selesai (SLA)',
        props.report.sla_date
            ? formatDateTime(props.report.sla_date)
            : 'Belum ditentukan',
    ],
    ['PIC', props.report.pic ?? 'Belum ditugaskan'],
    ['Dibuat', formatDateTime(props.report.created_at)],
];
</script>

<template>
    <Head :title="report.ticket_number" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <PageHeader :title="report.title" :description="report.ticket_number">
            <StatusBadge :status="report.status" />
            <span
                v-if="report.is_overdue"
                class="rounded-full bg-status-rejected-bg px-2.5 py-1 text-xs font-semibold text-status-rejected"
                >Terlambat</span
            >
        </PageHeader>

        <div v-if="can.update || can.cancel" class="flex flex-wrap gap-2">
            <Button v-if="can.update" as-child variant="outline" class="h-11">
                <Link :href="edit.url(report.id)"
                    ><Pencil class="size-4" /> Ubah laporan</Link
                >
            </Button>
            <Button
                v-if="can.cancel"
                variant="outline"
                class="h-11"
                @click="confirmCancel = true"
                >Batalkan laporan</Button
            >
        </div>

        <section
            v-if="report.rejection_reason"
            class="rounded-xl border border-destructive/40 bg-status-rejected-bg p-4 text-status-rejected"
            role="status"
        >
            <h2 class="font-semibold">Laporan ditolak</h2>
            <p class="mt-1 text-sm">{{ report.rejection_reason }}</p>
        </section>

        <section
            v-if="report.hold_reason && report.status === 'On_Hold'"
            class="rounded-xl border bg-status-on-hold-bg p-4 text-status-on-hold"
            role="status"
        >
            <h2 class="font-semibold">Pengerjaan ditunda</h2>
            <p class="mt-1 text-sm">{{ report.hold_reason }}</p>
        </section>

        <dl class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2">
            <div v-for="[label, value] in details" :key="label">
                <dt class="text-sm text-muted-foreground">{{ label }}</dt>
                <dd class="font-medium">{{ value }}</dd>
            </div>
        </dl>

        <section aria-labelledby="desc">
            <h2 id="desc" class="mb-1 font-semibold">Deskripsi</h2>
            <p class="whitespace-pre-line">{{ report.description }}</p>
        </section>

        <section v-if="report.damage_photos.length" aria-labelledby="photos">
            <h2 id="photos" class="mb-2 font-semibold">Foto kerusakan</h2>
            <ul class="grid grid-cols-3 gap-2">
                <li v-for="photo in report.damage_photos" :key="photo.id">
                    <a
                        :href="photo.url"
                        target="_blank"
                        rel="noopener"
                        class="block aspect-square overflow-hidden rounded-lg border focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <img
                            :src="photo.url"
                            alt="Foto kerusakan"
                            class="size-full object-cover"
                            loading="lazy"
                        />
                    </a>
                </li>
            </ul>
        </section>

        <section
            v-if="report.status === 'Closed'"
            class="grid gap-3 rounded-xl border p-4"
            aria-labelledby="done"
        >
            <h2 id="done" class="font-semibold">Hasil perbaikan</h2>
            <p class="whitespace-pre-line">{{ report.repair_action }}</p>
            <dl class="grid gap-3 sm:grid-cols-3">
                <div>
                    <dt class="text-sm text-muted-foreground">
                        Tanggal selesai
                    </dt>
                    <dd class="font-medium">
                        {{ formatDate(report.completed_on) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">Biaya</dt>
                    <dd class="font-medium">
                        {{ formatRupiah(report.repair_cost) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">Vendor</dt>
                    <dd class="font-medium">{{ report.vendor_name ?? '-' }}</dd>
                </div>
            </dl>
            <ul
                v-if="report.completion_photos.length"
                class="grid grid-cols-3 gap-2"
            >
                <li v-for="photo in report.completion_photos" :key="photo.id">
                    <a
                        :href="photo.url"
                        target="_blank"
                        rel="noopener"
                        class="block aspect-square overflow-hidden rounded-lg border focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <img
                            :src="photo.url"
                            alt="Foto bukti perbaikan"
                            class="size-full object-cover"
                            loading="lazy"
                        />
                    </a>
                </li>
            </ul>
        </section>

        <section aria-labelledby="timeline">
            <h2 id="timeline" class="mb-3 font-semibold">Riwayat</h2>
            <Timeline :items="report.timeline" />
        </section>
    </div>

    <ConfirmDialog
        v-model:open="confirmCancel"
        title="Batalkan laporan?"
        description="Laporan yang dibatalkan tidak bisa dibuka kembali."
        confirm-label="Ya, batalkan"
        destructive
        @confirm="doCancel"
    />
</template>

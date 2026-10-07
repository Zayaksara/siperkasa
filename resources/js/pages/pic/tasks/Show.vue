<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import OverdueBadge from '@/components/OverdueBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import PhotoUploader from '@/components/PhotoUploader.vue';
import RupiahInput from '@/components/RupiahInput.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import Timeline from '@/components/Timeline.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    formatDate,
    formatDateTime,
    formatRupiah,
    formatSlaRemaining,
} from '@/lib/format';
import { complete, hold, index, resume, start } from '@/routes/pic/tasks';

type Photo = { id: number; url: string };

const props = defineProps<{
    report: {
        id: number;
        ticket_number: string;
        title: string;
        description: string;
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
        reporter_nim_nip: string | null;
        asset: { asset_code: string; name: string } | null;
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
    can: { start: boolean; hold: boolean; resume: boolean; complete: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Tugas', href: index() }] },
});

// YYYY-MM-DD in Jakarta time (matches the server's "today").
const jakartaDate = (date: Date) =>
    new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(date);
const today = jakartaDate(new Date());
const minDate = computed(() => jakartaDate(new Date(props.report.created_at)));

const form = useForm({
    repair_action: '',
    completed_on: today,
    repair_cost: '' as string | number,
    vendor_name: '',
    photos: [] as File[],
});

const compressing = ref(false);
const acting = ref(false);
const busy = computed(() => acting.value || form.processing);

function post(url: string) {
    if (busy.value) return;
    acting.value = true;
    router.post(
        url,
        {},
        { preserveScroll: true, onFinish: () => (acting.value = false) },
    );
}

const holding = ref(false);
const holdError = ref<string | undefined>();

function doHold(reason?: string) {
    if (busy.value) return;
    acting.value = true;
    holdError.value = undefined;
    router.post(
        hold.url(props.report.id),
        { hold_reason: reason },
        {
            preserveScroll: true,
            onError: (errors) => (holdError.value = errors.hold_reason),
            onSuccess: () => (holding.value = false),
            onFinish: () => (acting.value = false),
        },
    );
}

const confirmComplete = ref(false);

function submitComplete() {
    if (busy.value || compressing.value) return;
    form.post(complete.url(props.report.id), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => (confirmComplete.value = false),
    });
}

// First photo error: "photos" or any "photos.N".
const photoError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;
    const key = Object.keys(errors).find(
        (k) => k === 'photos' || k.startsWith('photos.'),
    );

    return key ? errors[key] : undefined;
});

// Remaining SLA time, recomputed on mount and whenever the report props change.
const now = ref(Date.now());
onMounted(() => (now.value = Date.now()));
watch(
    () => [props.report.sla_date, props.report.status],
    () => (now.value = Date.now()),
);
const slaRemaining = computed(() =>
    props.report.status === 'Closed'
        ? null
        : formatSlaRemaining(props.report.sla_date, now.value),
);

const details = computed<[string, string][]>(() => [
    [
        'Pelapor',
        props.report.reporter
            ? `${props.report.reporter} (${props.report.reporter_nim_nip ?? '-'})`
            : 'Pengguna dihapus',
    ],
    ['Kategori', props.report.category ?? '-'],
    ['Lokasi', `${props.report.building ?? '-'}, ${props.report.room ?? '-'}`],
    [
        'Aset',
        props.report.asset
            ? `${props.report.asset.name} (${props.report.asset.asset_code})`
            : 'Tidak dipilih',
    ],
    ['Prioritas', props.report.admin_priority_label ?? '-'],
    [
        'Target selesai (SLA)',
        props.report.sla_date
            ? `${formatDateTime(props.report.sla_date)}${slaRemaining.value ? ` (${slaRemaining.value})` : ''}`
            : '-',
    ],
]);

const hasActions = computed(
    () => props.can.start || props.can.hold || props.can.resume,
);
</script>

<template>
    <Head :title="report.ticket_number" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 pb-24">
        <PageHeader :title="report.title" :description="report.ticket_number">
            <StatusBadge :status="report.status" :label="report.status_label" />
            <OverdueBadge v-if="report.is_overdue" />
        </PageHeader>

        <section
            v-if="report.hold_reason && report.status === 'On_Hold'"
            class="rounded-xl border bg-status-on-hold-bg p-4 text-status-on-hold"
        >
            <h2 class="font-semibold">Alasan penundaan</h2>
            <p class="mt-1 text-sm break-words whitespace-pre-line">
                {{ report.hold_reason }}
            </p>
        </section>

        <dl class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2">
            <div v-for="[label, value] in details" :key="label">
                <dt class="text-sm text-muted-foreground">{{ label }}</dt>
                <dd class="font-medium break-words">{{ value }}</dd>
            </div>
        </dl>

        <section aria-labelledby="desc">
            <h2 id="desc" class="mb-1 font-semibold">Deskripsi</h2>
            <p class="break-words whitespace-pre-line">
                {{ report.description }}
            </p>
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
            <p class="break-words whitespace-pre-line">
                {{ report.repair_action }}
            </p>
            <p class="text-sm break-words text-muted-foreground">
                Selesai {{ formatDate(report.completed_on) }} · Biaya
                {{ formatRupiah(report.repair_cost) }} · Vendor
                {{ report.vendor_name ?? '-' }}
            </p>
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

        <div
            v-if="hasActions"
            class="sticky bottom-14 z-30 -mx-4 flex flex-wrap gap-2 border-t bg-background p-4 md:bottom-0"
        >
            <Button
                v-if="can.start"
                class="h-11"
                :disabled="busy"
                data-test="start-button"
                @click="post(start.url(report.id))"
                >Mulai kerjakan</Button
            >
            <Button
                v-if="can.hold"
                variant="outline"
                class="h-11"
                :disabled="busy"
                data-test="hold-button"
                @click="holding = true"
                >Tunda (ada kendala)</Button
            >
            <Button
                v-if="can.resume"
                class="h-11"
                :disabled="busy"
                data-test="resume-button"
                @click="post(resume.url(report.id))"
                >Lanjutkan pengerjaan</Button
            >
        </div>

        <section
            v-if="can.complete"
            class="grid gap-4 rounded-xl border-2 border-primary/30 p-4"
            aria-labelledby="complete-h"
        >
            <h2 id="complete-h" class="text-lg font-semibold">
                Form penyelesaian
            </h2>
            <form class="grid gap-4" @submit.prevent="confirmComplete = true">
                <div class="grid gap-2">
                    <Label for="c-action">Tindakan perbaikan</Label>
                    <Textarea
                        id="c-action"
                        v-model="form.repair_action"
                        :rows="4"
                        required
                        :aria-invalid="!!form.errors.repair_action"
                    />
                    <InputError :message="form.errors.repair_action" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="c-date">Tanggal selesai</Label>
                        <Input
                            id="c-date"
                            v-model="form.completed_on"
                            type="date"
                            :min="minDate"
                            :max="today"
                            class="h-11"
                            required
                            :aria-invalid="!!form.errors.completed_on"
                        />
                        <InputError :message="form.errors.completed_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="c-cost">Biaya (Rupiah, opsional)</Label>
                        <RupiahInput
                            id="c-cost"
                            v-model="form.repair_cost"
                            placeholder="Contoh: 150.000"
                            :aria-invalid="!!form.errors.repair_cost"
                        />
                        <InputError :message="form.errors.repair_cost" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="c-vendor"
                        >Vendor / teknisi luar (opsional)</Label
                    >
                    <Input
                        id="c-vendor"
                        v-model="form.vendor_name"
                        class="h-11"
                        :aria-invalid="!!form.errors.vendor_name"
                    />
                    <InputError :message="form.errors.vendor_name" />
                </div>
                <div class="grid gap-2">
                    <Label id="c-photos-label"
                        >Foto bukti pengerjaan (1-3)</Label
                    >
                    <PhotoUploader
                        role="group"
                        aria-labelledby="c-photos-label"
                        v-model="form.photos"
                        :error="photoError"
                        @update:busy="compressing = $event"
                    />
                </div>
                <Button
                    type="submit"
                    class="h-11"
                    :disabled="busy || compressing"
                    data-test="complete-button"
                    >Selesaikan laporan</Button
                >
            </form>
        </section>

        <section aria-labelledby="timeline">
            <h2 id="timeline" class="mb-3 font-semibold">Riwayat</h2>
            <Timeline :items="report.timeline" />
        </section>
    </div>

    <ConfirmDialog
        v-model:open="holding"
        title="Tunda pengerjaan?"
        description="Jelaskan kendalanya. Pelapor akan melihat status tertunda."
        confirm-label="Tunda"
        reason-label="Alasan penundaan"
        :reason-min="10"
        :processing="acting"
        :error="holdError"
        @confirm="doHold"
    />

    <ConfirmDialog
        v-model:open="confirmComplete"
        title="Selesaikan laporan?"
        description="Setelah diselesaikan, laporan ditutup dan pelapor diberi tahu. Ini tidak bisa dibatalkan."
        confirm-label="Ya, selesaikan"
        :processing="busy || compressing"
        @confirm="submitComplete"
    />
</template>

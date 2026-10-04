<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CategoryCombobox from '@/components/CategoryCombobox.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import HoldBadge from '@/components/HoldBadge.vue';
import InputError from '@/components/InputError.vue';
import OverdueBadge from '@/components/OverdueBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import Timeline from '@/components/Timeline.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDate, formatDateTime, formatRupiah } from '@/lib/format';
import { approve, index, reassign, reject } from '@/routes/admin/reports';

type Photo = { id: number; url: string };

const props = defineProps<{
    report: {
        id: number;
        ticket_number: string;
        title: string;
        description: string;
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
        category_id: number | null;
        room: string | null;
        building: string | null;
        reporter: string | null;
        reporter_nim_nip: string | null;
        pic: string | null;
        admin: string | null;
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
    can: { approve: boolean; reject: boolean; reassign: boolean };
    pics: { id: number; name: string; active_tasks: number }[];
    categories: { id: number; name: string; default_pic_id: number | null }[];
    current_pic_id: number | null;
    suggested_pic_id: number | null;
    priorities: { value: string; label: string }[];
    sla_defaults: Record<string, string>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Laporan', href: index() }] },
});

const approveForm = useForm({
    title: props.report.title,
    category_id: (props.report.category_id ?? '') as string | number,
    admin_priority: props.report.user_priority,
    sla_date: props.sla_defaults[props.report.user_priority] ?? '',
    assigned_pic_id: (props.suggested_pic_id ?? '') as string | number,
});

const slaTouched = ref(false);
watch(
    () => approveForm.admin_priority,
    (priority) => {
        if (!slaTouched.value) {
            approveForm.sla_date =
                props.sla_defaults[priority] ?? approveForm.sla_date;
        }
    },
);

const suggestedPicId = ref<number | null>(props.suggested_pic_id);
const picTouched = ref(false);
watch(
    () => approveForm.category_id,
    (categoryId) => {
        const picId = props.categories.find(
            (c) => c.id === Number(categoryId),
        )?.default_pic_id;
        suggestedPicId.value =
            picId != null && props.pics.some((pic) => pic.id === picId)
                ? picId
                : null;

        if (!picTouched.value && suggestedPicId.value !== null) {
            approveForm.assigned_pic_id = suggestedPicId.value;
        }
    },
);

const busy = computed(
    () => approveForm.processing || reassignForm.processing || rejectBusy.value,
);
const noPics = computed(() => props.pics.length === 0);
const noOtherPics = computed(() => otherPics.value.length === 0);

function submitApprove() {
    if (busy.value || noPics.value) return;
    approveForm
        .transform((data) => {
            const { title, category_id, ...rest } = data;
            const payload: Record<string, unknown> = { ...rest };
            const newTitle = title.trim();

            if (newTitle !== '' && newTitle !== props.report.title) {
                payload.title = newTitle;
            }

            if (
                category_id !== '' &&
                Number(category_id) !== props.report.category_id
            ) {
                payload.category_id = category_id;
            }

            return payload as typeof data;
        })
        .post(approve.url(props.report.id), { preserveScroll: true });
}

const reassignForm = useForm({ assigned_pic_id: '' as string | number });
const otherPics = computed(() =>
    props.pics.filter((pic) => pic.id !== props.current_pic_id),
);

function submitReassign() {
    if (busy.value || noOtherPics.value) return;
    reassignForm.post(reassign.url(props.report.id), {
        preserveScroll: true,
        onSuccess: () => reassignForm.reset(),
    });
}

const rejecting = ref(false);
const rejectError = ref<string | undefined>();
const rejectBusy = ref(false);

function doReject(reason?: string) {
    if (busy.value) return;
    rejectBusy.value = true;
    rejectError.value = undefined;
    router.post(
        reject.url(props.report.id),
        { rejection_reason: reason },
        {
            preserveScroll: true,
            onError: (errors) => (rejectError.value = errors.rejection_reason),
            onSuccess: () => (rejecting.value = false),
            onFinish: () => (rejectBusy.value = false),
        },
    );
}

const details = computed<[string, string][]>(() => [
    [
        'Pelapor',
        props.report.reporter
            ? `${props.report.reporter} (${props.report.reporter_nim_nip ?? '-'})`
            : '-',
    ],
    ['Kategori', props.report.category ?? '-'],
    ['Lokasi', `${props.report.building ?? '-'}, ${props.report.room ?? '-'}`],
    [
        'Aset',
        props.report.asset
            ? `${props.report.asset.name} (${props.report.asset.asset_code})`
            : 'Tidak dipilih',
    ],
    ['Urgensi pelapor', props.report.user_priority_label],
    [
        'Prioritas admin',
        props.report.admin_priority_label ?? 'Belum ditentukan',
    ],
    ['PIC', props.report.pic ?? 'Belum ditugaskan'],
    [
        'Target selesai (SLA)',
        props.report.sla_date
            ? formatDateTime(props.report.sla_date)
            : 'Belum ditentukan',
    ],
    ['Dibuat', formatDateTime(props.report.created_at)],
]);
</script>

<template>
    <Head :title="report.ticket_number" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
        <PageHeader :title="report.title" :description="report.ticket_number">
            <StatusBadge :status="report.status" :label="report.status_label" />
            <HoldBadge
                v-if="report.status === 'On_Hold' && report.is_overdue"
            />
            <OverdueBadge v-if="report.is_overdue" />
        </PageHeader>

        <dl class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2">
            <div v-for="[label, value] in details" :key="label">
                <dt class="text-sm text-muted-foreground">{{ label }}</dt>
                <dd class="font-medium break-words">{{ value }}</dd>
            </div>
        </dl>

        <section aria-labelledby="desc">
            <h2 id="desc" class="mb-1 font-semibold">Deskripsi</h2>
            <p class="whitespace-pre-line">{{ report.description }}</p>
        </section>

        <section v-if="report.damage_photos.length" aria-labelledby="photos">
            <h2 id="photos" class="mb-2 font-semibold">Foto kerusakan</h2>
            <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4">
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
            v-if="report.rejection_reason"
            class="rounded-xl border bg-status-rejected-bg p-4 text-status-rejected"
        >
            <h2 class="font-semibold">Alasan penolakan</h2>
            <p class="mt-1 text-sm break-words whitespace-pre-line">
                {{ report.rejection_reason }}
            </p>
        </section>

        <section
            v-if="report.status === 'On_Hold' && report.hold_reason"
            class="rounded-xl border bg-status-on-hold-bg p-4 text-status-on-hold"
        >
            <h2 class="font-semibold">Alasan penundaan</h2>
            <p class="mt-1 text-sm break-words whitespace-pre-line">
                {{ report.hold_reason }}
            </p>
        </section>

        <section
            v-if="report.status === 'Closed'"
            class="grid gap-3 rounded-xl border p-4"
            aria-labelledby="done"
        >
            <h2 id="done" class="font-semibold">Hasil perbaikan</h2>
            <p class="whitespace-pre-line">{{ report.repair_action }}</p>
            <p class="text-sm text-muted-foreground">
                Selesai {{ formatDate(report.completed_on) }} · Biaya
                {{ formatRupiah(report.repair_cost) }} · Vendor
                {{ report.vendor_name ?? '-' }}
            </p>
            <ul
                v-if="report.completion_photos.length"
                class="grid grid-cols-3 gap-2 sm:grid-cols-4"
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

        <section
            v-if="can.approve"
            class="grid gap-4 rounded-xl border-2 border-primary/30 p-4"
            aria-labelledby="approve-h"
        >
            <h2 id="approve-h" class="text-lg font-semibold">
                Validasi laporan
            </h2>
            <p
                v-if="noPics"
                class="rounded-lg border border-dashed p-3 text-sm text-muted-foreground"
            >
                Belum ada PIC aktif. Tambahkan PIC di menu Pengguna.
            </p>
            <form class="grid gap-4" @submit.prevent="submitApprove">
                <div class="grid gap-2">
                    <Label for="a-title">Judul laporan</Label>
                    <Input
                        id="a-title"
                        v-model="approveForm.title"
                        class="h-11"
                        minlength="10"
                        maxlength="120"
                        placeholder="Contoh: AC ruang 201 bocor"
                        aria-describedby="a-title-help"
                        :aria-invalid="!!approveForm.errors.title"
                    />
                    <p id="a-title-help" class="text-xs text-muted-foreground">
                        Sebut barang + kerusakan + lokasi singkat. Bisa
                        dirapikan sebelum divalidasi.
                    </p>
                    <InputError :message="approveForm.errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label for="a-category">Kategori</Label>
                    <CategoryCombobox
                        id="a-category"
                        v-model="approveForm.category_id"
                        :categories="categories"
                        :invalid="!!approveForm.errors.category_id"
                    />
                    <InputError :message="approveForm.errors.category_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="a-priority">Prioritas</Label>
                    <NativeSelect
                        id="a-priority"
                        v-model="approveForm.admin_priority"
                        :aria-invalid="!!approveForm.errors.admin_priority"
                    >
                        <option
                            v-for="p in priorities"
                            :key="p.value"
                            :value="p.value"
                        >
                            {{ p.label }}
                        </option>
                    </NativeSelect>
                    <InputError :message="approveForm.errors.admin_priority" />
                </div>
                <div class="grid gap-2">
                    <Label for="a-sla">Target selesai (SLA)</Label>
                    <Input
                        id="a-sla"
                        v-model="approveForm.sla_date"
                        type="datetime-local"
                        class="h-11"
                        required
                        :aria-invalid="!!approveForm.errors.sla_date"
                        @input="slaTouched = true"
                    />
                    <p class="text-xs text-muted-foreground">
                        Terisi otomatis dari prioritas dan bisa diubah.
                    </p>
                    <InputError :message="approveForm.errors.sla_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="a-pic">PIC</Label>
                    <NativeSelect
                        id="a-pic"
                        v-model="approveForm.assigned_pic_id"
                        required
                        :aria-invalid="!!approveForm.errors.assigned_pic_id"
                        @change="picTouched = true"
                    >
                        <option value="" disabled>Pilih PIC</option>
                        <option
                            v-for="pic in pics"
                            :key="pic.id"
                            :value="pic.id"
                        >
                            {{ pic.name }} ({{ pic.active_tasks }} tugas
                            aktif){{
                                pic.id === suggestedPicId ? ' - disarankan' : ''
                            }}
                        </option>
                    </NativeSelect>
                    <InputError :message="approveForm.errors.assigned_pic_id" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="submit"
                        class="h-11"
                        :disabled="busy || noPics"
                        data-test="approve-button"
                        >Validasi dan tugaskan</Button
                    >
                    <Button
                        v-if="can.reject"
                        type="button"
                        variant="outline"
                        class="h-11 text-destructive"
                        :disabled="busy"
                        @click="rejecting = true"
                        >Tolak laporan</Button
                    >
                </div>
            </form>
        </section>

        <section
            v-if="can.reassign"
            class="grid gap-3 rounded-xl border p-4"
            aria-labelledby="reassign-h"
        >
            <h2 id="reassign-h" class="font-semibold">Pindahkan PIC</h2>
            <p
                v-if="noOtherPics"
                class="rounded-lg border border-dashed p-3 text-sm text-muted-foreground"
            >
                Belum ada PIC aktif lain. Tambahkan PIC di menu Pengguna.
            </p>
            <form
                class="grid gap-3 sm:grid-cols-[1fr_auto]"
                @submit.prevent="submitReassign"
            >
                <div class="grid gap-2">
                    <Label for="r-pic">PIC baru</Label>
                    <NativeSelect
                        id="r-pic"
                        v-model="reassignForm.assigned_pic_id"
                        required
                        :aria-invalid="!!reassignForm.errors.assigned_pic_id"
                    >
                        <option value="" disabled>Pilih PIC</option>
                        <option
                            v-for="pic in otherPics"
                            :key="pic.id"
                            :value="pic.id"
                        >
                            {{ pic.name }} ({{ pic.active_tasks }} tugas aktif)
                        </option>
                    </NativeSelect>
                    <InputError
                        :message="reassignForm.errors.assigned_pic_id"
                    />
                </div>
                <Button
                    type="submit"
                    class="h-11 self-start sm:mt-6"
                    :disabled="busy || noOtherPics"
                    >Pindahkan PIC</Button
                >
            </form>
        </section>

        <section aria-labelledby="timeline">
            <h2 id="timeline" class="mb-3 font-semibold">Riwayat</h2>
            <Timeline :items="report.timeline" />
        </section>
    </div>

    <ConfirmDialog
        v-model:open="rejecting"
        title="Tolak laporan?"
        description="Pelapor akan menerima alasan penolakan. Laporan yang ditolak tidak bisa dibuka lagi."
        confirm-label="Tolak laporan"
        reason-label="Alasan penolakan"
        :reason-min="10"
        :processing="rejectBusy"
        :error="rejectError"
        destructive
        @confirm="doReject"
    />
</template>

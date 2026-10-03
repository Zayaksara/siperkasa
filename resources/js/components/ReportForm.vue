<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AssetCombobox from '@/components/AssetCombobox.vue';
import type { AssetOption } from '@/components/AssetCombobox.vue';
import InputError from '@/components/InputError.vue';
import PhotoUploader from '@/components/PhotoUploader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/reports';

type Building = {
    id: number;
    code: string;
    name: string;
    rooms: { id: number; code: string; name: string }[];
};
type ReportData = {
    id: number;
    title: string;
    category_id: number;
    room_id: number;
    description: string;
    user_priority: string;
    asset: AssetOption | null;
    damage_photos: { id: number; url: string }[];
};

const props = defineProps<{
    mode: 'create' | 'edit';
    categories: { id: number; name: string }[];
    buildings: Building[];
    priorities: { value: string; label: string }[];
    report?: ReportData;
}>();

const me = computed(() => usePage().props.auth.user);

const initialBuilding = props.report
    ? (props.buildings.find((b) =>
          b.rooms.some((r) => r.id === props.report!.room_id),
      )?.id ?? '')
    : '';

const buildingId = ref<string | number>(initialBuilding);
const rooms = computed(
    () =>
        props.buildings.find((b) => b.id === Number(buildingId.value))?.rooms ??
        [],
);

const form = useForm({
    title: props.report?.title ?? '',
    category_id: (props.report?.category_id ?? '') as string | number,
    room_id: (props.report?.room_id ?? '') as string | number,
    asset: (props.report?.asset ?? null) as AssetOption | null,
    description: props.report?.description ?? '',
    user_priority: props.report?.user_priority ?? 'Medium',
    photos: [] as File[],
    remove_photo_ids: [] as number[],
});

watch(buildingId, () => {
    if (!rooms.value.some((room) => room.id === Number(form.room_id)))
        form.room_id = '';
});

const photoError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return (
        errors.photos ??
        Object.entries(errors).find(([key]) => key.startsWith('photos.'))?.[1]
    );
});

function submit() {
    form.transform((data) => {
        const { asset, ...rest } = data;
        const payload = { ...rest, asset_id: asset?.id ?? null };

        return props.mode === 'edit' ? { ...payload, _method: 'put' } : payload;
    });

    const url =
        props.mode === 'edit' ? update.url(props.report!.id) : store.url();
    form.post(url, { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <form class="grid gap-5 pb-4" @submit.prevent="submit">
        <section
            class="grid gap-1 rounded-xl border border-border bg-muted/40 p-3 text-sm"
            aria-label="Data pelapor"
        >
            <div>
                <span class="text-muted-foreground">Pelapor</span>
                <span class="font-medium">{{ me.name }}</span>
            </div>
            <div>
                <span class="text-muted-foreground">NIM/NIP</span>
                <span class="font-medium">{{ me.nim_nip }}</span>
            </div>
        </section>

        <div class="grid gap-2">
            <Label for="title">Judul laporan</Label>
            <Input
                id="title"
                v-model="form.title"
                class="h-11"
                maxlength="120"
                placeholder="Contoh: Proyektor tidak menyala"
                required
                :aria-invalid="!!form.errors.title"
            />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid gap-2">
            <Label for="category">Kategori</Label>
            <NativeSelect
                id="category"
                v-model="form.category_id"
                required
                :aria-invalid="!!form.errors.category_id"
            >
                <option value="" disabled>Pilih kategori</option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id"
                >
                    {{ category.name }}
                </option>
            </NativeSelect>
            <InputError :message="form.errors.category_id" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="building">Gedung</Label>
                <NativeSelect id="building" v-model="buildingId" required>
                    <option value="" disabled>Pilih gedung</option>
                    <option
                        v-for="building in buildings"
                        :key="building.id"
                        :value="building.id"
                    >
                        {{ building.name }}
                    </option>
                </NativeSelect>
            </div>
            <div class="grid gap-2">
                <Label for="room">Ruang</Label>
                <NativeSelect
                    id="room"
                    v-model="form.room_id"
                    :disabled="rooms.length === 0"
                    required
                    :aria-invalid="!!form.errors.room_id"
                >
                    <option value="" disabled>
                        {{ buildingId ? 'Pilih ruang' : 'Pilih gedung dulu' }}
                    </option>
                    <option
                        v-for="room in rooms"
                        :key="room.id"
                        :value="room.id"
                    >
                        {{ room.name }}
                    </option>
                </NativeSelect>
                <InputError :message="form.errors.room_id" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label>Aset terkait (opsional)</Label>
            <AssetCombobox v-model="form.asset" />
            <InputError
                :message="
                    (form.errors as Record<string, string | undefined>).asset_id
                "
            />
        </div>

        <div class="grid gap-2">
            <Label for="description">Deskripsi kerusakan</Label>
            <Textarea
                id="description"
                v-model="form.description"
                :rows="5"
                maxlength="2000"
                placeholder="Jelaskan apa yang rusak dan sejak kapan."
                required
                :aria-invalid="!!form.errors.description"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div class="grid gap-2">
            <Label for="priority">Urgensi menurut Anda</Label>
            <NativeSelect
                id="priority"
                v-model="form.user_priority"
                required
                :aria-invalid="!!form.errors.user_priority"
            >
                <option
                    v-for="priority in priorities"
                    :key="priority.value"
                    :value="priority.value"
                >
                    {{ priority.label }}
                </option>
            </NativeSelect>
            <InputError :message="form.errors.user_priority" />
        </div>

        <div class="grid gap-2">
            <Label>Foto kerusakan</Label>
            <PhotoUploader
                v-model="form.photos"
                v-model:removed-ids="form.remove_photo_ids"
                :existing="report?.damage_photos ?? []"
                :error="photoError"
            />
        </div>

        <div
            class="sticky bottom-14 z-30 -mx-4 flex gap-2 border-t border-border bg-background p-4 md:bottom-0"
        >
            <Button
                type="button"
                variant="outline"
                class="h-11"
                :disabled="form.processing"
                @click="
                    router.visit(
                        props.report ? show.url(props.report.id) : index.url(),
                    )
                "
            >
                Batal
            </Button>
            <Button
                type="submit"
                class="h-11 flex-1"
                :disabled="form.processing"
                data-test="submit-report"
            >
                {{
                    form.processing
                        ? 'Mengirim…'
                        : mode === 'edit'
                          ? 'Simpan perubahan'
                          : 'Kirim laporan'
                }}
            </Button>
        </div>
    </form>
</template>

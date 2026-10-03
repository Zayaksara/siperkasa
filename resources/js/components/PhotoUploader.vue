<script setup lang="ts">
import { Camera, ImagePlus, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { compressImage } from '@/lib/compressImage';

type Existing = { id: number; url: string };

const props = withDefaults(
    defineProps<{
        modelValue: File[];
        existing?: Existing[];
        removedIds?: number[];
        max?: number;
        error?: string;
    }>(),
    { existing: () => [], removedIds: () => [], max: 3, error: undefined },
);

const emit = defineEmits<{
    'update:modelValue': [files: File[]];
    'update:removedIds': [ids: number[]];
}>();

const busy = ref(false);
const keptExisting = computed(() =>
    props.existing.filter((photo) => !props.removedIds.includes(photo.id)),
);
const total = computed(
    () => keptExisting.value.length + props.modelValue.length,
);
const remaining = computed(() => props.max - total.value);
const locked = computed(() => remaining.value <= 0 || busy.value);

const previews = ref<{ file: File; url: string }[]>([]);

watch(
    () => props.modelValue,
    (files) => {
        const next = files.map(
            (file) =>
                previews.value.find((preview) => preview.file === file) ?? {
                    file,
                    url: URL.createObjectURL(file),
                },
        );
        previews.value
            .filter((preview) => !next.includes(preview))
            .forEach((preview) => URL.revokeObjectURL(preview.url));
        previews.value = next;
    },
    { immediate: true },
);

onBeforeUnmount(() =>
    previews.value.forEach((preview) => URL.revokeObjectURL(preview.url)),
);

async function add(event: Event) {
    const input = event.target as HTMLInputElement;
    const picked = Array.from(input.files ?? []).slice(
        0,
        Math.max(remaining.value, 0),
    );
    input.value = '';

    if (picked.length === 0) return;

    busy.value = true;
    try {
        const compressed = await Promise.all(
            picked.map((file) => compressImage(file)),
        );
        emit('update:modelValue', [...props.modelValue, ...compressed]);
    } finally {
        busy.value = false;
    }
}

const removeNew = (file: File) =>
    emit(
        'update:modelValue',
        props.modelValue.filter((item) => item !== file),
    );
const removeExisting = (id: number) =>
    emit('update:removedIds', [...props.removedIds, id]);

const removeButton =
    'absolute top-0 right-0 inline-flex size-11 items-center justify-center rounded-full text-foreground transition-colors hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:scale-95';
const pickLabel =
    'cursor-pointer has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50';
</script>

<template>
    <div class="grid gap-3">
        <ul
            v-if="keptExisting.length || previews.length"
            class="grid grid-cols-3 gap-2"
        >
            <li
                v-for="photo in keptExisting"
                :key="`e${photo.id}`"
                class="relative aspect-square overflow-hidden rounded-lg border border-border bg-muted"
            >
                <img
                    :src="photo.url"
                    alt="Foto yang sudah diunggah"
                    class="size-full object-cover"
                />
                <button
                    type="button"
                    :class="removeButton"
                    aria-label="Hapus foto yang sudah diunggah"
                    @click="removeExisting(photo.id)"
                >
                    <span
                        class="inline-flex size-7 items-center justify-center rounded-full bg-background/90 shadow"
                        ><X class="size-4"
                    /></span>
                </button>
            </li>
            <li
                v-for="preview in previews"
                :key="preview.url"
                class="relative aspect-square overflow-hidden rounded-lg border border-border bg-muted"
            >
                <img
                    :src="preview.url"
                    alt="Pratinjau foto baru"
                    class="size-full object-cover"
                />
                <button
                    type="button"
                    :class="removeButton"
                    aria-label="Hapus foto baru"
                    @click="removeNew(preview.file)"
                >
                    <span
                        class="inline-flex size-7 items-center justify-center rounded-full bg-background/90 shadow"
                        ><X class="size-4"
                    /></span>
                </button>
            </li>
        </ul>

        <div class="flex flex-wrap gap-2">
            <Button
                as-child
                variant="outline"
                class="h-11"
                :class="[
                    pickLabel,
                    { 'pointer-events-none opacity-50': locked },
                ]"
            >
                <label>
                    <Camera class="size-4" /> Ambil foto
                    <input
                        type="file"
                        accept="image/*"
                        capture="environment"
                        class="sr-only"
                        :disabled="locked"
                        @change="add"
                    />
                </label>
            </Button>
            <Button
                as-child
                variant="outline"
                class="h-11"
                :class="[
                    pickLabel,
                    { 'pointer-events-none opacity-50': locked },
                ]"
            >
                <label>
                    <ImagePlus class="size-4" /> Pilih dari galeri
                    <input
                        type="file"
                        accept="image/*"
                        multiple
                        class="sr-only"
                        :disabled="locked"
                        @change="add"
                    />
                </label>
            </Button>
        </div>

        <p class="text-xs text-muted-foreground" aria-live="polite">
            {{
                busy
                    ? 'Memproses foto…'
                    : `${total} dari maksimal ${max} foto. Minimal 1 foto.`
            }}
        </p>
        <p v-if="error" class="text-sm text-destructive" role="alert">
            {{ error }}
        </p>
    </div>
</template>

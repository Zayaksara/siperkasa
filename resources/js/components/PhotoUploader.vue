<script setup lang="ts">
import { Camera, ImagePlus, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
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
    'update:busy': [busy: boolean];
}>();

const busy = ref(false);
const localError = ref<string>();
const list = ref<HTMLElement>();
const galleryInput = ref<HTMLInputElement>();
const cameraInput = ref<HTMLInputElement>();

const keptExisting = computed(() =>
    props.existing.filter((photo) => !props.removedIds.includes(photo.id)),
);
const total = computed(
    () => keptExisting.value.length + props.modelValue.length,
);
const remaining = computed(() => props.max - total.value);
const locked = computed(() => remaining.value <= 0 || busy.value);
const shownError = computed(() => localError.value ?? props.error);

watch(busy, (value) => emit('update:busy', value));

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
    const picked = Array.from(input.files ?? []);
    input.value = '';
    localError.value = undefined;

    if (picked.length === 0) return;

    busy.value = true;
    try {
        const results = await Promise.allSettled(
            picked
                .slice(0, Math.max(remaining.value, 0))
                .map((file) => compressImage(file)),
        );
        const files = results.flatMap((result) =>
            result.status === 'fulfilled' ? [result.value] : [],
        );
        const failure = results.find((result) => result.status === 'rejected');

        if (failure) {
            localError.value = (failure.reason as Error).message;
        } else if (picked.length > remaining.value) {
            localError.value = `Maksimal ${props.max} foto.`;
        }

        if (files.length > 0) {
            emit('update:modelValue', [...props.modelValue, ...files]);
        }
    } finally {
        busy.value = false;
    }
}

function indexOf(event: Event): number {
    const buttons = Array.from(
        list.value?.querySelectorAll('[data-remove]') ?? [],
    );

    return buttons.indexOf(event.currentTarget as Element);
}

async function afterRemove(index: number) {
    await nextTick();

    const buttons =
        list.value?.querySelectorAll<HTMLButtonElement>('[data-remove]') ?? [];

    if (buttons.length > 0) {
        buttons[Math.min(index, buttons.length - 1)].focus();
    } else {
        (galleryInput.value ?? cameraInput.value)?.focus();
    }
}

function removeNew(file: File, event: Event) {
    const index = indexOf(event);
    localError.value = undefined;
    emit(
        'update:modelValue',
        props.modelValue.filter((item) => item !== file),
    );
    afterRemove(index);
}

function removeExisting(id: number, event: Event) {
    const index = indexOf(event);
    localError.value = undefined;
    emit('update:removedIds', [...props.removedIds, id]);
    afterRemove(index);
}

const removeButton =
    'absolute top-0 right-0 inline-flex size-11 items-center justify-center rounded-full text-foreground transition-colors hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:scale-95';
const pickLabel =
    'cursor-pointer has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50';
</script>

<template>
    <div class="grid gap-3">
        <ul
            v-if="keptExisting.length || previews.length"
            ref="list"
            class="grid grid-cols-3 gap-2"
        >
            <li
                v-for="(photo, index) in keptExisting"
                :key="`e${photo.id}`"
                class="relative aspect-square overflow-hidden rounded-lg border border-border bg-muted"
            >
                <img
                    :src="photo.url"
                    :alt="`Foto yang sudah diunggah ${index + 1}`"
                    class="size-full object-cover"
                />
                <button
                    type="button"
                    data-remove
                    :class="removeButton"
                    :aria-label="`Hapus foto yang sudah diunggah ${index + 1}`"
                    @click="removeExisting(photo.id, $event)"
                >
                    <span
                        class="inline-flex size-7 items-center justify-center rounded-full bg-background/90 shadow"
                        ><X class="size-4"
                    /></span>
                </button>
            </li>
            <li
                v-for="(preview, index) in previews"
                :key="preview.url"
                class="relative aspect-square overflow-hidden rounded-lg border border-border bg-muted"
            >
                <img
                    :src="preview.url"
                    :alt="`Pratinjau foto baru ${index + 1}`"
                    class="size-full object-cover"
                />
                <button
                    type="button"
                    data-remove
                    :class="removeButton"
                    :aria-label="`Hapus foto baru ${index + 1}`"
                    @click="removeNew(preview.file, $event)"
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
                        ref="cameraInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
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
                        ref="galleryInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
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
        <p v-if="shownError" class="text-sm text-destructive" role="alert">
            {{ shownError }}
        </p>
    </div>
</template>

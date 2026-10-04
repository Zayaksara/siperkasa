<script lang="ts">
export type CategoryOption = {
    id: number;
    name: string;
    examples?: string[];
};
</script>

<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { search } from '@/routes/categories';

const MIN_CHARS = 2;

const props = withDefaults(
    defineProps<{
        categories: CategoryOption[];
        id?: string;
        required?: boolean;
        invalid?: boolean;
    }>(),
    { id: undefined, required: false, invalid: false },
);

const model = defineModel<string | number>({ default: '' });

const uid = useId();
const listboxId = `${uid}-listbox`;
const optionId = (index: number) => `${uid}-option-${index}`;

const editing = ref(false);
const query = ref('');
const remote = ref<CategoryOption[]>([]);
const open = ref(false);
const active = ref(-1);
const loading = ref(false);
const failed = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
let sequence = 0;

const trimmed = computed(() => query.value.trim());
const searching = computed(() => trimmed.value.length >= MIN_CHARS);
const selectedName = computed(
    () =>
        props.categories.find((c) => c.id === Number(model.value))?.name ?? '',
);
const shown = computed(() =>
    editing.value ? query.value : selectedName.value,
);
const options = computed<CategoryOption[]>(() =>
    searching.value ? remote.value : props.categories,
);

const status = computed(() => {
    if (failed.value) return 'Pencarian gagal. Coba lagi.';
    if (loading.value) return 'Mencari kategori…';
    if (!editing.value) return '';
    if (searching.value) {
        return `${remote.value.length} kategori ditemukan.`;
    }

    return 'Ketik nama barang untuk mencari kategori, atau pilih dari daftar.';
});

watch(query, (value) => {
    clearTimeout(timer);
    sequence++;
    failed.value = false;
    active.value = -1;

    if (value.trim().length < MIN_CHARS) {
        loading.value = false;
        remote.value = [];

        return;
    }

    loading.value = true;
    timer = setTimeout(load, 300);
});

watch(active, async (index) => {
    if (index < 0) return;

    await nextTick();
    document
        .getElementById(optionId(index))
        ?.scrollIntoView({ block: 'nearest' });
});

async function load() {
    const current = ++sequence;
    loading.value = true;
    failed.value = false;

    try {
        const response = await fetch(
            search.url({ query: { q: trimmed.value } }),
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );
        if (!response.ok) throw new Error(String(response.status));

        const json = (await response.json()) as { data: CategoryOption[] };
        if (current !== sequence) return;

        remote.value = json.data;
        active.value = -1;
    } catch {
        if (current !== sequence) return;
        remote.value = [];
        failed.value = true;
    } finally {
        if (current === sequence) loading.value = false;
    }
}

function onFocus() {
    editing.value = true;
    query.value = '';
    open.value = true;
}

function onBlur() {
    clearTimeout(timer);
    sequence++;
    loading.value = false;
    editing.value = false;
    open.value = false;
    active.value = -1;
}

function onInput(event: Event) {
    query.value = (event.target as HTMLInputElement).value;
    open.value = true;
}

function choose(option: CategoryOption) {
    clearTimeout(timer);
    model.value = option.id;
    query.value = '';
    open.value = false;
    active.value = -1;
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowDown' && !open.value) {
        event.preventDefault();
        open.value = true;
        active.value = options.value.length > 0 ? 0 : -1;

        return;
    }

    if (!open.value) return;

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        active.value = Math.min(active.value + 1, options.value.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        active.value = Math.max(active.value - 1, 0);
    } else if (event.key === 'Enter') {
        // Never let Enter submit the parent form while the list is open.
        event.preventDefault();

        const option =
            active.value >= 0
                ? options.value[active.value]
                : loading.value
                  ? undefined
                  : options.value[0];
        if (option) choose(option);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        open.value = false;
    }
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="relative">
        <Input
            :id="id"
            :model-value="shown"
            class="h-11"
            role="combobox"
            aria-autocomplete="list"
            :aria-controls="listboxId"
            :aria-expanded="open"
            :aria-busy="loading"
            :aria-invalid="invalid"
            :aria-activedescendant="
                open && active >= 0 ? optionId(active) : undefined
            "
            :required="required"
            :placeholder="
                selectedName || 'Cari atau pilih kategori (mis. proyektor, AC)'
            "
            autocomplete="off"
            @focus="onFocus"
            @blur="onBlur"
            @input="onInput"
            @keydown="onKeydown"
        />
        <p
            class="mt-1 min-h-4 text-xs"
            :class="failed ? 'text-destructive' : 'text-muted-foreground'"
            aria-live="polite"
        >
            {{ status }}
        </p>
        <ul
            v-show="open && options.length > 0"
            :id="listboxId"
            role="listbox"
            aria-label="Daftar kategori"
            class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border border-border bg-popover text-popover-foreground shadow-md"
        >
            <li
                v-for="(option, index) in options"
                :id="optionId(index)"
                :key="option.id"
                role="option"
                :aria-selected="option.id === Number(model)"
                class="flex min-h-11 cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm transition-colors active:bg-accent"
                :class="
                    index === active
                        ? 'bg-accent text-accent-foreground'
                        : 'hover:bg-accent'
                "
                @mousedown.prevent="choose(option)"
            >
                <span class="min-w-0">
                    <span class="block truncate font-medium">{{
                        option.name
                    }}</span>
                    <span
                        v-if="option.examples && option.examples.length > 0"
                        class="block truncate text-muted-foreground"
                        >{{ option.examples.join(', ') }}</span
                    >
                </span>
                <Check
                    v-if="option.id === Number(model)"
                    class="size-4 shrink-0"
                    aria-hidden="true"
                />
            </li>
        </ul>
    </div>
</template>

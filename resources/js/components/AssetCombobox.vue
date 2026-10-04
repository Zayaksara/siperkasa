<script lang="ts">
export type AssetOption = {
    id: number;
    asset_code: string;
    name: string;
    brand: string | null;
    category: string | null;
    category_id?: number | null;
};
</script>

<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { search } from '@/routes/assets';

const MIN_CHARS = 2;

const model = defineModel<AssetOption | null>({ default: null });

const uid = useId();
const listboxId = `${uid}-listbox`;
const optionId = (index: number) => `${uid}-option-${index}`;

const query = ref('');
const results = ref<AssetOption[]>([]);
const open = ref(false);
const active = ref(-1);
const loading = ref(false);
const failed = ref(false);
const searched = ref(false);
const inputRef = ref<{ $el: HTMLElement }>();
const clearRef = ref<{ $el: HTMLElement }>();
let timer: ReturnType<typeof setTimeout> | undefined;
let sequence = 0;

const trimmed = computed(() => query.value.trim());

const status = computed(() => {
    if (failed.value) return 'Pencarian gagal. Coba lagi.';
    if (loading.value) return 'Mencari aset…';
    if (trimmed.value.length < MIN_CHARS) {
        return 'Ketik minimal 2 karakter untuk mencari aset.';
    }
    if (searched.value && results.value.length === 0) {
        return 'Aset tidak ditemukan.';
    }
    if (results.value.length > 0) {
        return `${results.value.length} aset ditemukan. Gunakan panah atas dan bawah lalu Enter untuk memilih.`;
    }

    return '';
});

watch(query, (value) => {
    clearTimeout(timer);
    sequence++;
    failed.value = false;

    if (value.trim().length < MIN_CHARS) {
        results.value = [];
        open.value = false;
        loading.value = false;
        searched.value = false;
        active.value = -1;

        return;
    }

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

        const json = (await response.json()) as { data: AssetOption[] };
        if (current !== sequence) return;

        results.value = json.data;
        active.value = -1;
        searched.value = true;
        open.value = json.data.length > 0;
    } catch {
        if (current !== sequence) return;
        results.value = [];
        failed.value = true;
        open.value = false;
    } finally {
        if (current === sequence) loading.value = false;
    }
}

async function choose(option: AssetOption) {
    clearTimeout(timer);
    model.value = option;
    query.value = '';
    results.value = [];
    open.value = false;

    await nextTick();
    clearRef.value?.$el.focus();
}

async function clear() {
    model.value = null;

    await nextTick();
    inputRef.value?.$el.focus();
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowDown' && !open.value && results.value.length > 0) {
        event.preventDefault();
        open.value = true;
        active.value = 0;

        return;
    }

    if (!open.value) return;

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        active.value = Math.min(active.value + 1, results.value.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        active.value = Math.max(active.value - 1, 0);
    } else if (event.key === 'Enter' && active.value >= 0) {
        event.preventDefault();
        choose(results.value[active.value]);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        open.value = false;
    }
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="relative">
        <div
            v-if="model"
            class="flex items-center justify-between gap-2 rounded-md border border-border bg-muted/40 p-3"
        >
            <div class="min-w-0">
                <p class="truncate font-medium">{{ model.name }}</p>
                <p class="truncate text-sm text-muted-foreground">
                    {{ model.asset_code }}
                </p>
            </div>
            <Button
                ref="clearRef"
                type="button"
                variant="outline"
                size="icon"
                class="size-11 shrink-0"
                aria-label="Hapus aset terpilih"
                @click="clear"
            >
                <X class="size-4" />
            </Button>
        </div>

        <template v-else>
            <Input
                ref="inputRef"
                v-model="query"
                class="h-11"
                role="combobox"
                aria-label="Cari aset"
                aria-autocomplete="list"
                :aria-controls="listboxId"
                :aria-expanded="open"
                :aria-busy="loading"
                :aria-activedescendant="
                    open && active >= 0 ? optionId(active) : undefined
                "
                placeholder="Cari kode atau nama aset (opsional)"
                autocomplete="off"
                @keydown="onKeydown"
                @blur="open = false"
            />
            <p
                class="mt-1 min-h-4 text-xs"
                :class="failed ? 'text-destructive' : 'text-muted-foreground'"
                aria-live="polite"
            >
                {{ status }}
            </p>
            <ul
                v-show="open && results.length > 0"
                :id="listboxId"
                role="listbox"
                aria-label="Hasil pencarian aset"
                class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border border-border bg-popover text-popover-foreground shadow-md"
            >
                <li
                    v-for="(option, index) in results"
                    :id="optionId(index)"
                    :key="option.id"
                    role="option"
                    :aria-selected="index === active"
                    class="min-h-11 cursor-pointer px-3 py-2 text-sm transition-colors active:bg-accent"
                    :class="
                        index === active
                            ? 'bg-accent text-accent-foreground'
                            : 'hover:bg-accent'
                    "
                    @mousedown.prevent="choose(option)"
                >
                    <span class="block truncate font-medium">{{
                        option.name
                    }}</span>
                    <span class="block truncate text-muted-foreground"
                        >{{ option.asset_code
                        }}{{ option.brand ? ` · ${option.brand}` : '' }}</span
                    >
                </li>
            </ul>
        </template>
    </div>
</template>

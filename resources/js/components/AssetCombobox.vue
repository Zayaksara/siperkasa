<script lang="ts">
export type AssetOption = {
    id: number;
    asset_code: string;
    name: string;
    brand: string | null;
    category: string | null;
};
</script>

<script setup lang="ts">
import { X } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { search } from '@/routes/assets';

const model = defineModel<AssetOption | null>({ default: null });

const query = ref('');
const results = ref<AssetOption[]>([]);
const open = ref(false);
const active = ref(-1);
const loading = ref(false);
const failed = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
let sequence = 0;

function onInput() {
    clearTimeout(timer);
    sequence++;
    failed.value = false;

    if (query.value.trim().length < 2) {
        results.value = [];
        open.value = false;
        loading.value = false;
        return;
    }

    timer = setTimeout(load, 300);
}

async function load() {
    const current = ++sequence;
    loading.value = true;
    failed.value = false;

    try {
        const response = await fetch(
            search.url({ query: { q: query.value.trim() } }),
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
        open.value = true;
    } catch {
        if (current !== sequence) return;
        results.value = [];
        failed.value = true;
        open.value = true;
    } finally {
        if (current === sequence) loading.value = false;
    }
}

function choose(option: AssetOption) {
    model.value = option;
    query.value = '';
    results.value = [];
    open.value = false;
}

function onKeydown(event: KeyboardEvent) {
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
                type="button"
                variant="outline"
                size="icon"
                class="size-11 shrink-0"
                aria-label="Hapus aset terpilih"
                @click="model = null"
            >
                <X class="size-4" />
            </Button>
        </div>

        <template v-else>
            <Input
                v-model="query"
                class="h-11"
                role="combobox"
                aria-label="Cari aset"
                aria-autocomplete="list"
                aria-controls="asset-listbox"
                :aria-expanded="open"
                :aria-busy="loading"
                :aria-activedescendant="
                    active >= 0 ? `asset-option-${active}` : undefined
                "
                placeholder="Cari kode atau nama aset (opsional)"
                autocomplete="off"
                @input="onInput"
                @keydown="onKeydown"
                @blur="open = false"
            />
            <p
                v-if="loading"
                class="mt-1 text-xs text-muted-foreground"
                aria-live="polite"
            >
                Mencari aset…
            </p>
            <ul
                v-show="open"
                id="asset-listbox"
                role="listbox"
                aria-label="Hasil pencarian aset"
                class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border border-border bg-popover text-popover-foreground shadow-md"
            >
                <li
                    v-if="failed"
                    class="p-3 text-sm text-destructive"
                    role="alert"
                >
                    Pencarian gagal. Coba lagi.
                </li>
                <li
                    v-else-if="results.length === 0 && !loading"
                    class="p-3 text-sm text-muted-foreground"
                >
                    Aset tidak ditemukan.
                </li>
                <li
                    v-for="(option, index) in results"
                    :id="`asset-option-${index}`"
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

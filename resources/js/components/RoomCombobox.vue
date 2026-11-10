<script lang="ts">
export type RoomOption = {
    id: number;
    code: string;
    name: string;
    floor_name: string | null;
};
</script>

<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed, nextTick, ref, useId, watch } from 'vue';
import { Input } from '@/components/ui/input';

const MAX_SHOWN = 200;

const props = withDefaults(
    defineProps<{
        rooms: RoomOption[];
        id?: string;
        required?: boolean;
        invalid?: boolean;
        disabled?: boolean;
        placeholder?: string;
    }>(),
    {
        id: undefined,
        required: false,
        invalid: false,
        disabled: false,
        placeholder: 'Cari atau pilih ruangan',
    },
);

const model = defineModel<string | number>({ default: '' });

const uid = useId();
const listboxId = `${uid}-listbox`;
const optionId = (index: number) => `${uid}-option-${index}`;

const editing = ref(false);
const query = ref('');
const open = ref(false);
const active = ref(-1);

const selected = computed(
    () => props.rooms.find((r) => r.id === Number(model.value)) ?? null,
);
const selectedLabel = computed(() =>
    selected.value
        ? selected.value.floor_name
            ? `${selected.value.name} · ${selected.value.floor_name}`
            : selected.value.name
        : '',
);
const shown = computed(() =>
    editing.value ? query.value : selectedLabel.value,
);

const matches = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (!q) return props.rooms;

    return props.rooms.filter((r) =>
        `${r.name} ${r.floor_name ?? ''} ${r.code}`.toLowerCase().includes(q),
    );
});
const options = computed(() => matches.value.slice(0, MAX_SHOWN));

// Flat list with a floor heading flag so keyboard indexes stay simple.
const items = computed(() =>
    options.value.map((room, index) => ({
        room,
        index,
        heading:
            index === 0 ||
            options.value[index - 1].floor_name !== room.floor_name
                ? (room.floor_name ?? 'Tanpa lantai')
                : null,
    })),
);

const status = computed(() => {
    if (props.disabled || !editing.value) return '';
    if (matches.value.length === 0) return 'Ruangan tidak ditemukan.';
    if (matches.value.length > MAX_SHOWN) {
        return `${matches.value.length} ruangan. Ketik untuk mempersempit.`;
    }

    return `${matches.value.length} ruangan.`;
});

watch(query, () => {
    active.value = -1;
});

watch(active, async (index) => {
    if (index < 0) return;

    await nextTick();
    document
        .getElementById(optionId(index))
        ?.scrollIntoView({ block: 'nearest' });
});

function onFocus() {
    editing.value = true;
    query.value = '';
    open.value = true;
}

function onBlur() {
    editing.value = false;
    open.value = false;
    active.value = -1;
}

function onInput(event: Event) {
    query.value = (event.target as HTMLInputElement).value;
    open.value = true;
}

function choose(room: RoomOption) {
    model.value = room.id;
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

        const room = options.value[active.value >= 0 ? active.value : 0];

        if (room) choose(room);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        open.value = false;
    }
}
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
            :aria-invalid="invalid"
            :aria-activedescendant="
                open && active >= 0 ? optionId(active) : undefined
            "
            :required="required && !selected"
            :disabled="disabled"
            :placeholder="selectedLabel || placeholder"
            autocomplete="off"
            @focus="onFocus"
            @blur="onBlur"
            @input="onInput"
            @keydown="onKeydown"
        />
        <p
            class="mt-1 min-h-4 text-xs text-muted-foreground"
            aria-live="polite"
        >
            {{ status }}
        </p>
        <ul
            v-show="open && options.length > 0"
            :id="listboxId"
            role="listbox"
            aria-label="Daftar ruangan"
            class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border border-border bg-popover text-popover-foreground shadow-md"
        >
            <template v-for="item in items" :key="item.room.id">
                <li
                    v-if="item.heading"
                    role="presentation"
                    class="sticky top-0 bg-muted px-3 py-1 text-xs font-medium text-muted-foreground"
                >
                    {{ item.heading }}
                </li>
                <li
                    :id="optionId(item.index)"
                    role="option"
                    :aria-selected="item.room.id === Number(model)"
                    class="flex min-h-11 cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm transition-colors active:bg-accent"
                    :class="
                        item.index === active
                            ? 'bg-accent text-accent-foreground'
                            : 'hover:bg-accent'
                    "
                    @mousedown.prevent="choose(item.room)"
                >
                    <span class="min-w-0 truncate font-medium">{{
                        item.room.name
                    }}</span>
                    <Check
                        v-if="item.room.id === Number(model)"
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    />
                </li>
            </template>
        </ul>
    </div>
</template>

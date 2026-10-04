<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { caretAfter, formatRupiahInput, parseRupiah } from '@/lib/rupiah';

defineOptions({ inheritAttrs: false });

const props = defineProps<{ modelValue: string | number | null }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const toRaw = (value: string | number | null) =>
    value === null ? '' : String(value);

const display = ref(formatRupiahInput(toRaw(props.modelValue)));

watch(
    () => props.modelValue,
    (value) => {
        // Ignore echoes of our own emit; only resync on outside changes.
        if (parseRupiah(display.value) !== toRaw(value)) {
            display.value = formatRupiahInput(toRaw(value));
        }
    },
);

async function onInput(event: Event) {
    const el = event.target as HTMLInputElement;
    const typed = el.value;
    const caret = el.selectionStart ?? typed.length;
    const raw = parseRupiah(typed);
    const formatted = formatRupiahInput(raw);

    display.value = formatted;
    // Same text can leave the DOM stale (e.g. a rejected letter), so force it.
    el.value = formatted;
    emit('update:modelValue', raw);

    await nextTick();
    const next = caretAfter(typed, caret, formatted);
    el.setSelectionRange(next, next);
}
</script>

<template>
    <div class="relative">
        <span
            class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted-foreground"
            aria-hidden="true"
            >Rp</span
        >
        <Input
            v-bind="$attrs"
            :model-value="display"
            type="text"
            inputmode="decimal"
            autocomplete="off"
            class="h-11 pl-10"
            @input="onInput"
        />
    </div>
</template>

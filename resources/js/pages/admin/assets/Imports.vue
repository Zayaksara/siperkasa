<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import { index, store } from '@/routes/admin/asset-imports';

type ImportRow = {
    id: number;
    filename: string;
    status: 'queued' | 'processing' | 'done' | 'failed';
    status_label: string;
    inserted: number;
    updated: number;
    failed: number;
    errors: { line: number; message: string }[];
    user: string | null;
    created_at: string;
};

const props = defineProps<{ imports: ImportRow[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Impor Aset', href: index() }] },
});

const form = useForm<{ file: File | null }>({ file: null });

function submit() {
    form.post(store.url(), {
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
}

const running = computed(() =>
    props.imports.some(
        (i) => i.status === 'queued' || i.status === 'processing',
    ),
);
let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    timer = setInterval(() => {
        if (running.value) router.reload({ only: ['imports'] });
    }, 3000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <Head title="Impor Aset" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Impor Aset BMN"
            description="Unggah file Excel BMN (.xlsx, maks 20 MB). Aset yang sudah ada akan diperbarui."
        />

        <form
            class="grid gap-2 rounded-xl border p-4 sm:max-w-xl"
            @submit.prevent="submit"
        >
            <Label for="file">Berkas Excel</Label>
            <Input
                id="file"
                type="file"
                accept=".xlsx"
                class="h-11"
                @input="
                    form.file =
                        ($event.target as HTMLInputElement).files?.[0] ?? null
                "
            />
            <InputError :message="form.errors.file" />
            <Button
                type="submit"
                class="h-11 w-fit"
                :disabled="form.processing || !form.file"
                >Unggah dan impor</Button
            >
        </form>

        <EmptyState
            v-if="imports.length === 0"
            title="Belum ada impor"
            description="Riwayat impor akan tampil di sini."
        />

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="item in imports"
                :key="item.id"
                class="flex flex-col gap-1 p-3"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium">{{ item.filename }}</span>
                    <span
                        class="text-sm font-semibold"
                        :class="
                            item.status === 'failed'
                                ? 'text-destructive'
                                : item.status === 'done'
                                  ? 'text-status-closed'
                                  : 'text-status-in-progress'
                        "
                    >
                        {{ item.status_label }}
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ formatDateTime(item.created_at) }} ·
                    {{ item.user ?? 'Sistem' }} · ditambah {{ item.inserted }},
                    diperbarui {{ item.updated }}, gagal {{ item.failed }}
                </p>
                <ul
                    v-if="item.errors.length"
                    class="mt-1 list-disc pl-5 text-sm text-destructive"
                >
                    <li v-for="(error, i) in item.errors.slice(0, 5)" :key="i">
                        Baris {{ error.line }}: {{ error.message }}
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</template>

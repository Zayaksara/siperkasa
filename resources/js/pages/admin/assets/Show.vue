<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import { formatDate, formatRupiah } from '@/lib/format';
import { index } from '@/routes/admin/assets';

const props = defineProps<{
    asset: {
        id: number;
        asset_code: string;
        name: string;
        brand: string | null;
        type: string | null;
        jenis_bmn: string;
        golongan: string;
        condition: string | null;
        acquired_at: string | null;
        acquisition_value: string | null;
        category: string | null;
        bmn_data: Record<string, unknown>;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Aset', href: index() }] },
});

const summary: [string, string][] = [
    ['Kode aset', props.asset.asset_code],
    [
        'Merk / tipe',
        [props.asset.brand, props.asset.type].filter(Boolean).join(' / ') ||
            '-',
    ],
    ['Jenis BMN', props.asset.jenis_bmn],
    ['Golongan', props.asset.golongan],
    ['Kategori', props.asset.category ?? '-'],
    ['Kondisi', props.asset.condition ?? '-'],
    ['Tanggal perolehan', formatDate(props.asset.acquired_at)],
    ['Nilai perolehan', formatRupiah(props.asset.acquisition_value)],
];
</script>

<template>
    <Head :title="asset.name" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="asset.name"
            description="Data BMN bersifat read-only."
        />

        <dl class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2">
            <div v-for="[label, value] in summary" :key="label">
                <dt class="text-sm text-muted-foreground">{{ label }}</dt>
                <dd class="font-medium break-words">{{ value }}</dd>
            </div>
        </dl>

        <section aria-labelledby="bmn-heading">
            <h2 id="bmn-heading" class="mb-2 font-semibold">
                Seluruh kolom BMN
            </h2>
            <dl
                class="grid gap-x-6 gap-y-2 rounded-xl border p-4 text-sm sm:grid-cols-2"
            >
                <div
                    v-for="(value, key) in asset.bmn_data"
                    :key="key"
                    class="flex flex-col"
                >
                    <dt class="text-muted-foreground">{{ key }}</dt>
                    <dd class="break-words">{{ value ?? '-' }}</dd>
                </div>
            </dl>
        </section>
    </div>
</template>

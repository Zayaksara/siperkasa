<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { index, show } from '@/routes/admin/assets';

type AssetRow = {
    id: number;
    asset_code: string;
    name: string;
    brand: string | null;
    golongan: string;
    condition: string | null;
    category: string | null;
};

const props = defineProps<{
    assets: {
        data: AssetRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        q?: string;
        category_id?: string | number;
        golongan?: string;
        condition?: string;
    };
    categories: { id: number; name: string }[];
    conditions: string[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Aset', href: index() }] },
});

const q = ref(props.filters.q ?? '');
const category = ref(String(props.filters.category_id ?? ''));
const golongan = ref(props.filters.golongan ?? '');
const condition = ref(props.filters.condition ?? '');

function apply() {
    router.get(
        index.url(),
        {
            q: q.value || undefined,
            category_id: category.value || undefined,
            golongan: golongan.value || undefined,
            condition: condition.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Aset BMN" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader
            title="Aset BMN"
            description="Data aset hanya bisa dilihat. Perbarui lewat impor."
        />

        <form class="grid gap-2 md:grid-cols-5" @submit.prevent="apply">
            <Input
                v-model="q"
                class="h-11 md:col-span-2"
                placeholder="Cari kode atau nama aset"
                aria-label="Cari aset"
            />
            <NativeSelect v-model="category" aria-label="Kategori">
                <option value="">Semua kategori</option>
                <option
                    v-for="c in categories"
                    :key="c.id"
                    :value="String(c.id)"
                >
                    {{ c.name }}
                </option>
            </NativeSelect>
            <NativeSelect v-model="golongan" aria-label="Golongan">
                <option value="">IT &amp; NON-IT</option>
                <option value="IT">IT</option>
                <option value="NON-IT">NON-IT</option>
            </NativeSelect>
            <NativeSelect v-model="condition" aria-label="Kondisi">
                <option value="">Semua kondisi</option>
                <option v-for="c in conditions" :key="c" :value="c">
                    {{ c }}
                </option>
            </NativeSelect>
            <Button type="submit" class="h-11 md:col-span-5 md:w-fit"
                >Terapkan filter</Button
            >
        </form>

        <EmptyState
            v-if="assets.data.length === 0"
            title="Aset tidak ditemukan"
            description="Ubah filter atau impor data BMN terlebih dahulu."
        />

        <ul v-else class="divide-y rounded-xl border">
            <li v-for="asset in assets.data" :key="asset.id">
                <Link
                    :href="show.url(asset.id)"
                    class="flex flex-col gap-1 p-3 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none md:flex-row md:items-center md:justify-between"
                >
                    <span class="min-w-0">
                        <span class="block truncate font-medium">{{
                            asset.name
                        }}</span>
                        <span class="block text-sm text-muted-foreground"
                            >{{ asset.asset_code }} ·
                            {{ asset.brand ?? 'Tanpa merk' }}</span
                        >
                    </span>
                    <span class="text-sm text-muted-foreground">
                        {{ asset.category ?? 'Tanpa kategori' }} ·
                        {{ asset.golongan }} · {{ asset.condition ?? '-' }}
                    </span>
                </Link>
            </li>
        </ul>

        <Pagination :links="assets.links" />
    </div>
</template>

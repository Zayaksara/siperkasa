<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, RefreshCw, Search, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import SiberMapMark from '@/components/admin/SiberMapMark.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDateTime } from '@/lib/format';
import { motion, useMotion } from '@/lib/motion';
import { destroy, index, show, store, update } from '@/routes/admin/buildings';
import { sync } from '@/routes/admin/sibermap';

type Row = {
    id: number;
    code: string;
    name: string;
    campus_name: string | null;
    sibermap_id: number | null;
    rooms_count: number;
};

const props = defineProps<{
    buildings: Row[];
    campuses: string[];
    lastSyncedAt: string | null;
    filters: { q?: string; campus?: string };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Gedung & Ruang', href: index() }] },
});

const q = ref(props.filters.q ?? '');
const campus = ref(props.filters.campus ?? '');

function search() {
    router.get(
        index.url(),
        { q: q.value || undefined, campus: campus.value || undefined },
        { preserveState: true, replace: true },
    );
}

const syncing = ref(false);

function runSync() {
    router.post(
        sync.url(),
        {},
        {
            preserveScroll: true,
            onStart: () => (syncing.value = true),
            onFinish: () => (syncing.value = false),
        },
    );
}

const root = ref<HTMLElement | null>(null);
let entered = false;
useMotion(root, ({ gsap }) => {
    if (entered) return;
    entered = true;
    gsap.from('[data-row]', {
        autoAlpha: 0,
        y: motion.rise / 2,
        duration: 0.4,
        stagger: 0.04,
        clearProps: 'transform,opacity,visibility',
    });
});

const open = ref(false);
const editing = ref<Row | null>(null);
const toDelete = ref<Row | null>(null);
const form = useForm({ code: '', name: '' });

function create() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function edit(row: Row) {
    editing.value = row;
    form.code = row.code;
    form.name = row.name;
    form.clearErrors();
    open.value = true;
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (editing.value) form.put(update.url(editing.value.id), options);
    else form.post(store.url(), options);
}

function remove() {
    if (!toDelete.value) return;
    router.delete(destroy.url(toDelete.value.id), {
        preserveScroll: true,
        onFinish: () => (toDelete.value = null),
    });
}
</script>

<template>
    <Head title="Gedung & Ruang" />

    <div ref="root" class="flex min-w-0 flex-col gap-4 p-4">
        <PageHeader
            title="Gedung & Ruang"
            description="Lokasi kerusakan dipilih pelapor dari daftar ini."
        >
            <Button class="h-11" @click="create"
                ><Plus class="size-4" /> Tambah gedung</Button
            >
        </PageHeader>

        <div
            class="flex flex-col gap-3 rounded-xl border bg-card p-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-muted-foreground">
                {{
                    lastSyncedAt
                        ? `Terakhir sinkron: ${formatDateTime(lastSyncedAt)}`
                        : 'Belum pernah sinkron'
                }}
            </p>
            <Button
                variant="outline"
                class="h-11"
                :disabled="syncing"
                @click="runSync"
                ><RefreshCw
                    class="size-4"
                    :class="{ 'animate-spin': syncing }"
                />
                {{ syncing ? 'Menyinkronkan...' : 'Sinkron SiberMap' }}</Button
            >
        </div>

        <form
            role="search"
            class="flex flex-col gap-2 sm:flex-row"
            @submit.prevent="search"
        >
            <Input
                v-model="q"
                class="h-11 flex-1"
                placeholder="Cari kode atau nama gedung"
                aria-label="Cari gedung"
            />
            <NativeSelect
                v-model="campus"
                class="h-11 sm:w-56"
                aria-label="Filter kampus"
                @change="search"
            >
                <option value="">Semua kampus</option>
                <option v-for="c in campuses" :key="c" :value="c">
                    {{ c }}
                </option>
            </NativeSelect>
            <Button type="submit" variant="outline" class="h-11"
                ><Search class="size-4" /> Cari</Button
            >
        </form>

        <EmptyState
            v-if="buildings.length === 0 && (filters.q || filters.campus)"
            title="Gedung tidak ditemukan"
            description="Ubah kata kunci atau filter kampus."
        />
        <EmptyState
            v-else-if="buildings.length === 0"
            title="Belum ada gedung"
        />

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="row in buildings"
                :key="row.id"
                data-row
                class="flex items-center justify-between gap-3 p-3"
            >
                <Link
                    :href="show.url(row.id)"
                    class="min-w-0 flex-1 rounded focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <p class="flex items-center gap-2 font-medium">
                        <span class="truncate"
                            >{{ row.code }} · {{ row.name }}</span
                        >
                        <SiberMapMark v-if="row.sibermap_id !== null" />
                    </p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ row.campus_name ?? 'Kampus belum diisi' }} ·
                        {{ row.rooms_count }} ruang
                    </p>
                </Link>
                <SiberMapMark v-if="row.sibermap_id !== null" lock />
                <div v-else class="flex shrink-0 gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-11"
                        :aria-label="`Ubah ${row.name}`"
                        @click="edit(row)"
                        ><Pencil class="size-4"
                    /></Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-11"
                        :aria-label="`Hapus ${row.name}`"
                        @click="toDelete = row"
                        ><Trash2 class="size-4"
                    /></Button>
                </div>
            </li>
        </ul>
    </div>

    <Dialog v-model:open="open">
        <DialogContent>
            <form class="grid gap-4" @submit.prevent="save">
                <DialogHeader
                    ><DialogTitle>{{
                        editing ? 'Ubah gedung' : 'Tambah gedung'
                    }}</DialogTitle></DialogHeader
                >
                <div class="grid gap-2">
                    <Label for="b-code">Kode</Label>
                    <Input
                        id="b-code"
                        v-model="form.code"
                        class="h-11"
                        required
                    />
                    <InputError :message="form.errors.code" />
                </div>
                <div class="grid gap-2">
                    <Label for="b-name">Nama gedung</Label>
                    <Input
                        id="b-name"
                        v-model="form.name"
                        class="h-11"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11"
                        @click="open = false"
                        >Batal</Button
                    >
                    <Button
                        type="submit"
                        class="h-11"
                        :disabled="form.processing"
                        >Simpan</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="toDelete !== null"
        title="Hapus gedung?"
        :description="`Gedung ${toDelete?.name} akan dihapus.`"
        confirm-label="Hapus"
        destructive
        @update:open="(value) => !value && (toDelete = null)"
        @confirm="remove"
    />
</template>

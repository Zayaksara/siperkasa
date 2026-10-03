<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
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
import { destroy, index, show, store, update } from '@/routes/admin/buildings';

type Row = { id: number; code: string; name: string; rooms_count: number };

defineProps<{ buildings: Row[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Gedung & Ruang', href: index() }] },
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

    <div class="flex flex-col gap-4 p-4">
        <PageHeader
            title="Gedung & Ruang"
            description="Lokasi kerusakan dipilih pelapor dari daftar ini."
        >
            <Button class="h-11" @click="create"
                ><Plus class="size-4" /> Tambah gedung</Button
            >
        </PageHeader>

        <EmptyState v-if="buildings.length === 0" title="Belum ada gedung" />

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="row in buildings"
                :key="row.id"
                class="flex items-center justify-between gap-3 p-3"
            >
                <Link
                    :href="show.url(row.id)"
                    class="min-w-0 flex-1 rounded focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <p class="truncate font-medium">
                        {{ row.code }} · {{ row.name }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ row.rooms_count }} ruang
                    </p>
                </Link>
                <div class="flex shrink-0 gap-1">
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

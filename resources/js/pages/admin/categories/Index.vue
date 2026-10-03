<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
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
import { NativeSelect } from '@/components/ui/native-select';
import { destroy, index, store, update } from '@/routes/admin/categories';

type Row = {
    id: number;
    name: string;
    default_pic_id: number | null;
    default_pic_name: string | null;
    assets_count: number;
};

defineProps<{ categories: Row[]; pics: { id: number; name: string }[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Kategori', href: index() }] },
});

const open = ref(false);
const editing = ref<Row | null>(null);
const toDelete = ref<Row | null>(null);
const form = useForm({ name: '', default_pic_id: '' as string | number });

function create() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function edit(row: Row) {
    editing.value = row;
    form.name = row.name;
    form.default_pic_id = row.default_pic_id ?? '';
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
    <Head title="Kategori" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader
            title="Kategori"
            description="Kelompok aset dan PIC default yang disarankan saat validasi laporan."
        >
            <Button class="h-11" @click="create"
                ><Plus class="size-4" /> Tambah kategori</Button
            >
        </PageHeader>

        <EmptyState v-if="categories.length === 0" title="Belum ada kategori" />

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="row in categories"
                :key="row.id"
                class="flex items-center justify-between gap-3 p-3"
            >
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ row.name }}</p>
                    <p class="text-sm text-muted-foreground">
                        PIC: {{ row.default_pic_name ?? 'belum diatur' }} ·
                        {{ row.assets_count }} aset
                    </p>
                </div>
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
                        editing ? 'Ubah kategori' : 'Tambah kategori'
                    }}</DialogTitle></DialogHeader
                >
                <div class="grid gap-2">
                    <Label for="cat-name">Nama kategori</Label>
                    <Input
                        id="cat-name"
                        v-model="form.name"
                        class="h-11"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="cat-pic">PIC default</Label>
                    <NativeSelect id="cat-pic" v-model="form.default_pic_id">
                        <option value="">Belum diatur</option>
                        <option
                            v-for="pic in pics"
                            :key="pic.id"
                            :value="pic.id"
                        >
                            {{ pic.name }}
                        </option>
                    </NativeSelect>
                    <InputError :message="form.errors.default_pic_id" />
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
        title="Hapus kategori?"
        :description="`Kategori ${toDelete?.name} akan dihapus.`"
        confirm-label="Hapus"
        destructive
        @update:open="(value) => !value && (toDelete = null)"
        @confirm="remove"
    />
</template>

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
import { index } from '@/routes/admin/buildings';
import { store as storeRoom } from '@/routes/admin/buildings/rooms';
import {
    destroy as destroyRoom,
    update as updateRoom,
} from '@/routes/admin/rooms';

type Room = { id: number; code: string; name: string; floor: number | null };

const props = defineProps<{
    building: { id: number; code: string; name: string };
    rooms: Room[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Gedung & Ruang', href: index() }] },
});

const open = ref(false);
const editing = ref<Room | null>(null);
const toDelete = ref<Room | null>(null);
const form = useForm({ code: '', name: '', floor: '' as string | number });

function create() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function edit(room: Room) {
    editing.value = room;
    form.code = room.code;
    form.name = room.name;
    form.floor = room.floor ?? '';
    form.clearErrors();
    open.value = true;
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (editing.value) form.put(updateRoom.url(editing.value.id), options);
    else form.post(storeRoom.url(props.building.id), options);
}

function remove() {
    if (!toDelete.value) return;
    router.delete(destroyRoom.url(toDelete.value.id), {
        preserveScroll: true,
        onFinish: () => (toDelete.value = null),
    });
}
</script>

<template>
    <Head :title="`Ruang ${building.name}`" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader
            :title="`${building.code} · ${building.name}`"
            description="Daftar ruang di gedung ini."
        >
            <Button class="h-11" @click="create"
                ><Plus class="size-4" /> Tambah ruang</Button
            >
        </PageHeader>

        <EmptyState
            v-if="rooms.length === 0"
            title="Belum ada ruang"
            description="Tambahkan ruang agar bisa dipilih saat melapor."
        />

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="room in rooms"
                :key="room.id"
                class="flex items-center justify-between gap-3 p-3"
            >
                <div class="min-w-0">
                    <p class="truncate font-medium">
                        {{ room.code }} · {{ room.name }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Lantai {{ room.floor ?? '-' }}
                    </p>
                </div>
                <div class="flex shrink-0 gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-11"
                        :aria-label="`Ubah ${room.name}`"
                        @click="edit(room)"
                        ><Pencil class="size-4"
                    /></Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-11"
                        :aria-label="`Hapus ${room.name}`"
                        @click="toDelete = room"
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
                        editing ? 'Ubah ruang' : 'Tambah ruang'
                    }}</DialogTitle></DialogHeader
                >
                <div class="grid gap-2">
                    <Label for="r-code">Kode ruang</Label>
                    <Input
                        id="r-code"
                        v-model="form.code"
                        class="h-11"
                        required
                    />
                    <InputError :message="form.errors.code" />
                </div>
                <div class="grid gap-2">
                    <Label for="r-name">Nama ruang</Label>
                    <Input
                        id="r-name"
                        v-model="form.name"
                        class="h-11"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="r-floor">Lantai (opsional)</Label>
                    <Input
                        id="r-floor"
                        v-model="form.floor"
                        type="number"
                        class="h-11"
                    />
                    <InputError :message="form.errors.floor" />
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
        title="Hapus ruang?"
        :description="`Ruang ${toDelete?.name} akan dihapus.`"
        confirm-label="Hapus"
        destructive
        @update:open="(value) => !value && (toDelete = null)"
        @confirm="remove"
    />
</template>

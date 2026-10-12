<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    KeyRound,
    Pencil,
    Plus,
    Power,
    RotateCcw,
    Trash2,
    Upload,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
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
// Wayfinder renames the reserved word `import` to `importMethod`.
import {
    active,
    importMethod as importUsers,
    destroy,
    index,
    password as setPassword,
    restore,
    store,
    update,
} from '@/routes/admin/users';

type Row = {
    id: number;
    nim_nip: string;
    name: string;
    email: string | null;
    study_program: string | null;
    role: string;
    role_label: string;
    is_active: boolean;
    deleted: boolean;
    is_self: boolean;
    is_super_admin: boolean;
};
type ImportResult = {
    created: number;
    updated: number;
    failed: number;
    errors: { line: number; message: string }[];
    generated: { nim_nip: string; password: string }[];
};

const props = defineProps<{
    users: {
        data: Row[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q?: string; role?: string; status?: string };
    roles: { value: string; label: string }[];
    importResult: ImportResult | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Pengguna', href: index() }] },
});

const me = computed(() => usePage().props.auth.user.id);
const q = ref(props.filters.q ?? '');
const role = ref(props.filters.role ?? '');
const status = ref(props.filters.status ?? '');

function applyFilters() {
    router.get(
        index.url(),
        {
            q: q.value || undefined,
            role: role.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

const formOpen = ref(false);
const editing = ref<Row | null>(null);
const form = useForm({
    nim_nip: '',
    name: '',
    email: '',
    role: 'user',
    password: '',
});

function create() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    formOpen.value = true;
}

function edit(row: Row) {
    editing.value = row;
    form.nim_nip = row.nim_nip;
    form.name = row.name;
    form.email = row.email ?? '';
    form.role = row.role;
    form.password = '';
    form.clearErrors();
    formOpen.value = true;
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (formOpen.value = false),
    };

    if (editing.value) form.put(update.url(editing.value.id), options);
    else form.post(store.url(), options);
}

const pwUser = ref<Row | null>(null);
const pwForm = useForm({ password: '' });

function savePassword() {
    if (!pwUser.value) return;
    pwForm.put(setPassword.url(pwUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            pwUser.value = null;
            pwForm.reset();
        },
    });
}

const toggling = ref<Row | null>(null);

function confirmToggle() {
    if (!toggling.value) return;
    router.patch(
        active.url(toggling.value.id),
        { is_active: !toggling.value.is_active },
        { preserveScroll: true, onFinish: () => (toggling.value = null) },
    );
}

const removing = ref<Row | null>(null);
const restoring = ref<Row | null>(null);

function confirmRemove() {
    if (!removing.value) return;
    router.delete(destroy.url(removing.value.id), {
        preserveScroll: true,
        onError: (errors) => toast.error(errors.user ?? 'Akun gagal dihapus.'),
        onFinish: () => (removing.value = null),
    });
}

function confirmRestore() {
    if (!restoring.value) return;
    router.post(
        restore.url(restoring.value.id),
        {},
        {
            preserveScroll: true,
            onError: (errors) =>
                toast.error(errors.user ?? 'Akun gagal dipulihkan.'),
            onFinish: () => (restoring.value = null),
        },
    );
}

const importForm = useForm<{ file: File | null }>({ file: null });

function submitImport() {
    importForm.post(importUsers.url(), {
        forceFormData: true,
        onSuccess: () => importForm.reset(),
    });
}
</script>

<template>
    <Head title="Pengguna" />

    <div class="flex flex-col gap-4 p-4">
        <PageHeader
            title="Pengguna"
            description="Akun dibuat dan dikelola admin. Akun yang dihapus disembunyikan dan bisa dipulihkan."
        >
            <Button class="h-11" @click="create"
                ><Plus class="size-4" /> Tambah pengguna</Button
            >
        </PageHeader>

        <form class="grid gap-2 md:grid-cols-4" @submit.prevent="applyFilters">
            <Input
                v-model="q"
                class="h-11 md:col-span-2"
                placeholder="Cari nama atau NIM/NIP"
                aria-label="Cari pengguna"
            />
            <NativeSelect v-model="role" aria-label="Peran">
                <option value="">Semua peran</option>
                <option v-for="r in roles" :key="r.value" :value="r.value">
                    {{ r.label }}
                </option>
            </NativeSelect>
            <NativeSelect v-model="status" aria-label="Status">
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
                <option value="trashed">Terhapus</option>
            </NativeSelect>
            <Button type="submit" class="h-11 md:col-span-4 md:w-fit"
                >Terapkan filter</Button
            >
        </form>

        <section
            v-if="importResult"
            class="rounded-xl border border-primary/40 bg-secondary p-4 text-sm"
            role="status"
        >
            <p class="font-semibold">
                Impor selesai: {{ importResult.created }} dibuat,
                {{ importResult.updated }} diperbarui,
                {{ importResult.failed }} gagal.
            </p>
            <ul
                v-if="importResult.errors.length"
                class="mt-2 list-disc pl-5 text-destructive"
            >
                <li v-for="(error, i) in importResult.errors" :key="i">
                    Baris {{ error.line }}: {{ error.message }}
                </li>
            </ul>
            <div v-if="importResult.generated.length" class="mt-3">
                <p class="font-medium">
                    Password awal (hanya tampil sekali, catat sekarang):
                </p>
                <ul class="mt-1 grid gap-1 font-mono">
                    <li v-for="g in importResult.generated" :key="g.nim_nip">
                        {{ g.nim_nip }} → {{ g.password }}
                    </li>
                </ul>
            </div>
        </section>

        <EmptyState
            v-if="users.data.length === 0"
            title="Pengguna tidak ditemukan"
            description="Ubah filter atau tambah pengguna baru."
        />

        <ul v-else class="divide-y rounded-xl border">
            <li
                v-for="row in users.data"
                :key="row.id"
                class="flex flex-col gap-2 p-3 md:flex-row md:items-center md:justify-between"
            >
                <div class="min-w-0">
                    <p class="truncate font-medium">
                        {{ row.name }}
                        <span
                            v-if="row.deleted"
                            class="ml-1 rounded bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive"
                            >Terhapus</span
                        >
                        <span
                            v-else-if="!row.is_active"
                            class="ml-1 rounded bg-status-cancelled-bg px-2 py-0.5 text-xs font-semibold text-status-cancelled"
                            >Nonaktif</span
                        >
                    </p>
                    <p
                        v-if="row.study_program"
                        class="truncate text-sm text-muted-foreground"
                    >
                        {{ row.study_program }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ row.nim_nip }} · {{ row.role_label }} ·
                        {{ row.email ?? 'tanpa email' }}
                    </p>
                </div>
                <div class="flex shrink-0 gap-1">
                    <Button
                        v-if="row.deleted"
                        variant="outline"
                        class="h-11"
                        :aria-label="`Pulihkan ${row.name}`"
                        @click="restoring = row"
                        ><RotateCcw class="size-4" /> Pulihkan</Button
                    >
                    <template v-else>
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
                            :aria-label="`Ganti password ${row.name}`"
                            @click="pwUser = row"
                            ><KeyRound class="size-4"
                        /></Button>
                        <Button
                            variant="outline"
                            size="icon"
                            class="size-11"
                            :disabled="row.id === me"
                            :aria-label="
                                row.is_active
                                    ? `Nonaktifkan ${row.name}`
                                    : `Aktifkan ${row.name}`
                            "
                            @click="toggling = row"
                        >
                            <Power class="size-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            class="size-11 text-destructive hover:text-destructive"
                            :disabled="row.is_self || row.is_super_admin"
                            :aria-label="`Hapus ${row.name}`"
                            @click="removing = row"
                            ><Trash2 class="size-4"
                        /></Button>
                    </template>
                </div>
            </li>
        </ul>

        <Pagination :links="users.links" />

        <section class="rounded-xl border p-4">
            <h2 class="font-semibold">Impor pengguna</h2>
            <p class="text-sm text-muted-foreground">
                File .xlsx atau .csv dengan kolom
                <code>nim_nip, name, email, role, password</code> (role: user,
                admin, pic). Password kosong akan dibuat otomatis untuk pengguna
                baru.
            </p>
            <form
                class="mt-3 grid gap-2 sm:max-w-xl"
                @submit.prevent="submitImport"
            >
                <Input
                    type="file"
                    accept=".xlsx,.csv,.txt"
                    class="h-11"
                    aria-label="Berkas impor pengguna"
                    @input="
                        importForm.file =
                            ($event.target as HTMLInputElement).files?.[0] ??
                            null
                    "
                />
                <InputError :message="importForm.errors.file" />
                <Button
                    type="submit"
                    class="h-11 w-fit"
                    :disabled="importForm.processing || !importForm.file"
                    ><Upload class="size-4" /> Impor</Button
                >
            </form>
        </section>
    </div>

    <Dialog v-model:open="formOpen">
        <DialogContent>
            <form class="grid gap-4" @submit.prevent="save">
                <DialogHeader
                    ><DialogTitle>{{
                        editing ? 'Ubah pengguna' : 'Tambah pengguna'
                    }}</DialogTitle></DialogHeader
                >
                <div class="grid gap-2">
                    <Label for="u-nim">NIM/NIP</Label>
                    <Input
                        id="u-nim"
                        v-model="form.nim_nip"
                        class="h-11"
                        required
                    />
                    <InputError :message="form.errors.nim_nip" />
                </div>
                <div class="grid gap-2">
                    <Label for="u-name">Nama</Label>
                    <Input
                        id="u-name"
                        v-model="form.name"
                        class="h-11"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="u-email">Email (opsional)</Label>
                    <Input
                        id="u-email"
                        v-model="form.email"
                        type="email"
                        class="h-11"
                    />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="u-role">Peran</Label>
                    <NativeSelect id="u-role" v-model="form.role">
                        <option
                            v-for="r in roles"
                            :key="r.value"
                            :value="r.value"
                        >
                            {{ r.label }}
                        </option>
                    </NativeSelect>
                    <InputError :message="form.errors.role" />
                </div>
                <div v-if="!editing" class="grid gap-2">
                    <Label for="u-pass">Password awal</Label>
                    <Input
                        id="u-pass"
                        v-model="form.password"
                        type="text"
                        class="h-11"
                        required
                        minlength="8"
                    />
                    <InputError :message="form.errors.password" />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11"
                        @click="formOpen = false"
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

    <Dialog :open="pwUser !== null" @update:open="(v) => !v && (pwUser = null)">
        <DialogContent>
            <form class="grid gap-4" @submit.prevent="savePassword">
                <DialogHeader
                    ><DialogTitle
                        >Ganti password {{ pwUser?.name }}</DialogTitle
                    ></DialogHeader
                >
                <div class="grid gap-2">
                    <Label for="pw-new">Password baru</Label>
                    <Input
                        id="pw-new"
                        v-model="pwForm.password"
                        type="text"
                        class="h-11"
                        required
                        minlength="8"
                    />
                    <InputError :message="pwForm.errors.password" />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11"
                        @click="pwUser = null"
                        >Batal</Button
                    >
                    <Button
                        type="submit"
                        class="h-11"
                        :disabled="pwForm.processing"
                        >Simpan</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="toggling !== null"
        :title="toggling?.is_active ? 'Nonaktifkan akun?' : 'Aktifkan akun?'"
        :description="
            toggling?.is_active
                ? `${toggling?.name} tidak akan bisa masuk sampai diaktifkan lagi.`
                : `${toggling?.name} bisa masuk kembali.`
        "
        :confirm-label="toggling?.is_active ? 'Nonaktifkan' : 'Aktifkan'"
        :destructive="toggling?.is_active"
        @update:open="(value) => !value && (toggling = null)"
        @confirm="confirmToggle"
    />

    <ConfirmDialog
        :open="removing !== null"
        title="Hapus akun?"
        :description="`Hapus akun ${removing?.name}? Akun disembunyikan dan tidak bisa login. Riwayat laporan tetap tersimpan.`"
        confirm-label="Hapus"
        destructive
        @update:open="(value) => !value && (removing = null)"
        @confirm="confirmRemove"
    />

    <ConfirmDialog
        :open="restoring !== null"
        title="Pulihkan akun?"
        :description="`${restoring?.name} bisa masuk kembali dan muncul lagi di daftar pengguna.`"
        confirm-label="Pulihkan"
        @update:open="(value) => !value && (restoring = null)"
        @confirm="confirmRestore"
    />
</template>

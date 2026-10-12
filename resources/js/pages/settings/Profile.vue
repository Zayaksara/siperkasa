<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

type PortalProfile = {
    portal_role: string | null;
    faculty: string | null;
    study_program: string | null;
    degree_level: string | null;
    entry_year: number | null;
    phone: string | null;
};

const props = defineProps<{
    canEditEmail: boolean;
    portalProfile: PortalProfile | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Profil', href: edit() }],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const canManageSecurity = computed(() => page.props.canManageSecurity);

const description = computed(() =>
    props.canEditEmail
        ? 'Nama dan NIM/NIP diatur oleh admin. Anda bisa mengubah email.'
        : 'Data profil Anda.',
);

const lockNote = computed(() =>
    props.portalProfile
        ? 'Data profil mengikuti portal kampus dan diperbarui setiap login.'
        : 'Data profil diatur oleh admin. Hubungi admin untuk perubahan.',
);

const portalRows = computed(() => {
    const p = props.portalProfile;

    if (!p) {
        return [];
    }

    const program = [p.study_program, p.degree_level].filter(Boolean).join(' ');

    return [
        { id: 'portal_role', label: 'Jenis akun', value: p.portal_role },
        { id: 'faculty', label: 'Fakultas', value: p.faculty },
        { id: 'study_program', label: 'Program studi', value: program },
        { id: 'entry_year', label: 'Angkatan', value: p.entry_year },
        { id: 'phone', label: 'No. HP', value: p.phone },
    ].filter((row) => row.value !== null && row.value !== '');
});
</script>

<template>
    <Head title="Profil" />

    <h1 class="sr-only">Profil</h1>

    <div class="flex flex-col space-y-6">
        <Heading variant="small" title="Profil" :description="description" />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="nim_nip">NIM/NIP</Label>
                <Input
                    id="nim_nip"
                    :model-value="user.nim_nip"
                    disabled
                    class="h-11"
                />
            </div>

            <div class="grid gap-2">
                <Label for="name">Nama</Label>
                <Input
                    id="name"
                    :model-value="user.name"
                    disabled
                    class="h-11"
                />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email (opsional)</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    :default-value="user.email ?? ''"
                    :disabled="!canEditEmail"
                    autocomplete="email"
                    class="h-11"
                />
                <InputError :message="errors.email" />
            </div>

            <div v-for="row in portalRows" :key="row.id" class="grid gap-2">
                <Label :for="row.id">{{ row.label }}</Label>
                <Input
                    :id="row.id"
                    :model-value="String(row.value)"
                    disabled
                    class="h-11"
                />
            </div>

            <Button
                v-if="canEditEmail"
                :disabled="processing"
                class="h-11"
                data-test="update-profile-button"
            >
                Simpan
            </Button>
        </Form>

        <div
            v-if="!canEditEmail || !canManageSecurity"
            class="space-y-2 rounded-lg border bg-muted p-4 text-sm text-foreground"
        >
            <p v-if="!canEditEmail" data-test="profile-lock-note">
                {{ lockNote }}
            </p>
            <p v-if="!canManageSecurity" data-test="security-managed-note">
                Keamanan akun dikelola portal kampus. Ganti password lewat
                portal kampus.
            </p>
        </div>
    </div>
</template>

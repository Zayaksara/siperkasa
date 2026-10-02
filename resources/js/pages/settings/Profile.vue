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

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Profil', href: edit() }],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profil" />

    <h1 class="sr-only">Profil</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profil"
            description="Nama dan NIM/NIP diatur oleh admin. Anda bisa mengubah email."
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="nim_nip">NIM/NIP</Label>
                <Input id="nim_nip" :model-value="user.nim_nip" disabled />
            </div>

            <div class="grid gap-2">
                <Label for="name">Nama</Label>
                <Input id="name" :model-value="user.name" disabled />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email (opsional)</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    :default-value="user.email ?? ''"
                    autocomplete="email"
                    class="h-11"
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                :disabled="processing"
                class="h-11"
                data-test="update-profile-button"
            >
                Simpan
            </Button>
        </Form>
    </div>
</template>

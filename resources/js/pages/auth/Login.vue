<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

defineOptions({
    layout: {
        title: 'Masuk ke SiPerkasa',
        description: 'Gunakan NIM/NIP dan password yang diberikan admin.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Masuk" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-status-closed"
        role="status"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="nim_nip">NIM/NIP</Label>
                <Input
                    id="nim_nip"
                    type="text"
                    name="nim_nip"
                    inputmode="numeric"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="username"
                    placeholder="Masukkan NIM atau NIP"
                    class="h-11"
                />
                <InputError :message="errors.nim_nip" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <Label for="remember" class="flex items-center gap-3">
                <Checkbox id="remember" name="remember" :tabindex="3" />
                <span>Ingat saya</span>
            </Label>

            <Button
                type="submit"
                class="h-11 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Masuk
            </Button>
        </div>
    </Form>
</template>

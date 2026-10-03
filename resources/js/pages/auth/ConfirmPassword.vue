<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Konfirmasi password',
        description:
            'Bagian ini butuh keamanan tambahan. Masukkan password Anda sekali lagi untuk melanjutkan.',
    },
});
</script>

<template>
    <Head title="Konfirmasi password" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        label="Konfirmasi dengan passkey"
        loading-label="Mengonfirmasi..."
        separator="Atau konfirmasi dengan password"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label htmlFor="password" class="text-[15px]">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="block h-11 w-full rounded-xl bg-background px-3.5 pr-12 md:text-[15px]"
                    required
                    autocomplete="current-password"
                    :aria-invalid="errors.password ? true : undefined"
                    :aria-describedby="
                        errors.password ? 'password-error' : undefined
                    "
                    autofocus
                />

                <InputError id="password-error" :message="errors.password" />
            </div>

            <Button
                class="h-12 w-full rounded-full text-base font-semibold transition-[background-color,scale] duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background active:scale-[0.98] motion-reduce:transition-none"
                :disabled="processing"
                :aria-busy="processing"
                data-test="confirm-password-button"
            >
                <Spinner v-if="processing" />
                Konfirmasi password
            </Button>
        </div>
    </Form>
</template>

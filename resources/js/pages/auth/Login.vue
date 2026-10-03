<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { shake } from '@/lib/motion';
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

// Subtle shake on failed sign-in (skipped with reduced motion). The error
// text itself is still announced by InputError (role="alert").
const formShell = useTemplateRef<HTMLElement>('formShell');
</script>

<template>
    <Head title="Masuk" />

    <div
        v-if="status"
        class="mb-6 flex items-start gap-2.5 rounded-xl bg-status-closed-bg px-4 py-3 text-[15px] font-medium text-status-closed"
        role="status"
    >
        <CircleCheck class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <span class="min-w-0 break-words">{{ status }}</span>
    </div>

    <PasskeyVerify
        label="Masuk dengan passkey"
        loading-label="Memverifikasi passkey..."
        separator="Atau masuk dengan NIM/NIP"
    />

    <div ref="formShell">
        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
            @error="shake(formShell)"
        >
            <div class="grid gap-2">
                <Label for="nim_nip" class="text-[15px]">NIM/NIP</Label>
                <Input
                    id="nim_nip"
                    type="text"
                    name="nim_nip"
                    required
                    v-focus
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    placeholder="Masukkan NIM atau NIP"
                    :aria-invalid="errors.nim_nip ? true : undefined"
                    :aria-describedby="
                        errors.nim_nip ? 'nim_nip-error' : undefined
                    "
                    class="h-11 rounded-xl bg-background px-3.5 md:text-[15px]"
                />
                <InputError id="nim_nip-error" :message="errors.nim_nip" />
            </div>

            <div class="grid gap-2">
                <Label for="password" class="text-[15px]">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Masukkan password"
                    :aria-invalid="errors.password ? true : undefined"
                    :aria-describedby="
                        errors.password ? 'password-error' : undefined
                    "
                    class="h-11 rounded-xl bg-background px-3.5 pr-12 md:text-[15px]"
                />
                <InputError id="password-error" :message="errors.password" />
            </div>

            <Label
                for="remember"
                class="-my-1 flex min-h-11 w-fit cursor-pointer items-center gap-3 text-[15px] font-normal"
            >
                <Checkbox
                    id="remember"
                    name="remember"
                    class="size-5 rounded-md"
                />
                <span>Ingat saya di perangkat ini</span>
            </Label>

            <Button
                type="submit"
                class="h-12 w-full rounded-full text-base font-semibold transition-[background-color,translate,scale,box-shadow] duration-200 hover:-translate-y-0.5 hover:shadow-[0_8px_28px_-6px_color-mix(in_oklab,var(--primary)_45%,transparent)] focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background active:translate-y-0 active:scale-[0.98] motion-reduce:transition-none motion-reduce:hover:translate-y-0"
                :disabled="processing"
                :aria-busy="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Sedang masuk...' : 'Masuk' }}
            </Button>
        </Form>
    </div>

    <p class="mt-8 text-[13px] leading-relaxed text-muted-foreground">
        Lupa password atau belum punya akun? Hubungi admin Sarpras untuk
        mengatur ulang akses.
    </p>
</template>

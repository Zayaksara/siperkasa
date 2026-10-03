<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, useTemplateRef, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { shake } from '@/lib/motion';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);
const formShell = useTemplateRef<HTMLElement>('formShell');
const code = ref<string>('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Kode pemulihan',
            description:
                'Masukkan salah satu kode pemulihan darurat untuk mengonfirmasi akses ke akun Anda.',
            buttonText: 'masuk dengan kode autentikasi',
        };
    }

    return {
        title: 'Kode autentikasi',
        description: 'Masukkan kode 6 digit dari aplikasi autentikator Anda.',
        buttonText: 'masuk dengan kode pemulihan',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head title="Verifikasi dua langkah" />

    <div ref="formShell" class="space-y-6">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                @error="
                    code = '';
                    shake(formShell);
                "
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div
                    class="flex flex-col items-center justify-center space-y-3 text-center"
                >
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            v-model="code"
                            :maxlength="6"
                            :disabled="processing"
                            aria-label="Kode autentikasi"
                            :aria-invalid="errors.code ? true : undefined"
                            :aria-describedby="
                                errors.code ? 'code-error' : undefined
                            "
                            autofocus
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <InputError id="code-error" :message="errors.code" />
                </div>
                <Button
                    type="submit"
                    class="h-12 w-full rounded-full text-base font-semibold transition-[background-color,scale] duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background active:scale-[0.98] motion-reduce:transition-none"
                    :disabled="processing"
                    :aria-busy="processing"
                >
                    <Spinner v-if="processing" />
                    Lanjutkan
                </Button>
                <div class="text-center text-sm text-muted-foreground">
                    <span>atau </span>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center rounded-md px-1 font-medium text-primary underline decoration-primary/40 underline-offset-4 transition-colors duration-150 hover:decoration-current focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:text-foreground motion-reduce:transition-none"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                @error="shake(formShell)"
                #default="{ errors, processing, clearErrors }"
            >
                <Input
                    name="recovery_code"
                    type="text"
                    placeholder="Masukkan kode pemulihan"
                    aria-label="Kode pemulihan"
                    autocomplete="one-time-code"
                    :aria-invalid="errors.recovery_code ? true : undefined"
                    :aria-describedby="
                        errors.recovery_code ? 'recovery_code-error' : undefined
                    "
                    class="h-11 rounded-xl bg-background px-3.5 md:text-[15px]"
                    v-focus
                    required
                />
                <InputError
                    id="recovery_code-error"
                    :message="errors.recovery_code"
                />
                <Button
                    type="submit"
                    class="h-12 w-full rounded-full text-base font-semibold transition-[background-color,scale] duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background active:scale-[0.98] motion-reduce:transition-none"
                    :disabled="processing"
                    :aria-busy="processing"
                >
                    <Spinner v-if="processing" />
                    Lanjutkan
                </Button>

                <div class="text-center text-sm text-muted-foreground">
                    <span>atau </span>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center rounded-md px-1 font-medium text-primary underline decoration-primary/40 underline-offset-4 transition-colors duration-150 hover:decoration-current focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:text-foreground motion-reduce:transition-none"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>
    </div>
</template>

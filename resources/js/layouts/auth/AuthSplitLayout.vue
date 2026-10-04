<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, ClipboardCheck, Send, Wrench } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';
import { motion, useMotion } from '@/lib/motion';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const steps = [
    { label: 'Dilaporkan', icon: Send },
    { label: 'Divalidasi', icon: ClipboardCheck },
    { label: 'Dikerjakan', icon: Wrench },
    { label: 'Selesai', icon: Check },
];

const root = useTemplateRef<HTMLElement>('root');

/*
 * Entrance: the form card rises and its parts settle in order, so the field
 * is usable within ~0.5s. On lg the brand panel's journey line draws across
 * at the same time. Markup is final without JS or with reduced motion.
 */
useMotion(root, ({ gsap, isDesktop }) => {
    const q = gsap.utils.selector(root.value);
    const tl = gsap.timeline();

    // opacity only (never visibility:hidden) on the form: autofocus ran
    // before this and a hidden element would drop focus to <body>.
    tl.from(q('.auth-card'), {
        opacity: 0,
        y: motion.rise,
        duration: 0.6,
    }).from(
        q('.auth-head > *, .auth-body > *'),
        { opacity: 0, y: 8, duration: 0.45, stagger: 0.06 },
        '<0.1',
    );

    if (isDesktop) {
        tl.from(
            q('.auth-brand-in'),
            { autoAlpha: 0, y: motion.rise, duration: 0.7, stagger: 0.1 },
            0,
        )
            .fromTo(
                q('.auth-line'),
                { scaleX: 0 },
                { scaleX: 1, duration: 1.2, ease: 'power2.inOut' },
                0.3,
            )
            .from(
                q('.auth-dot'),
                { scale: 0.6, autoAlpha: 0, duration: 0.35, stagger: 0.3 },
                0.3,
            );
    }
});
</script>

<template>
    <div
        ref="root"
        class="min-h-svh overflow-x-clip bg-landing-bg font-sans text-landing-fg antialiased [color-scheme:dark] lg:grid lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]"
    >
        <!-- Brand panel: desktop only. Same journey as the landing hero. -->
        <aside
            class="relative hidden flex-col justify-between border-r border-landing-line p-10 lg:flex xl:p-14"
        >
            <Link
                :href="home()"
                class="auth-brand-in flex min-h-11 items-center gap-3 self-start rounded-md focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:ring-offset-2 focus-visible:ring-offset-landing-bg focus-visible:outline-none"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-full bg-landing-fg"
                >
                    <BrandLogo :size="24" />
                </span>
                <span class="flex flex-col">
                    <span
                        class="text-[17.5px] leading-tight font-semibold text-landing-fg"
                        >SiPerkasa</span
                    >
                    <span class="text-[13px] text-landing-muted"
                        >UIN Siber Syekh Nurjati Cirebon</span
                    >
                </span>
            </Link>

            <div class="max-w-lg">
                <p
                    class="auth-brand-in text-[44px] leading-[1.05] font-semibold tracking-[-0.03em] text-balance text-landing-fg xl:text-[56px]"
                >
                    Satu akun untuk melapor dan memantau perbaikan.
                </p>
                <p
                    class="auth-brand-in mt-5 max-w-md text-[17.5px] leading-relaxed text-pretty text-landing-muted"
                >
                    Mahasiswa, dosen, dan tenaga kependidikan masuk dengan
                    NIM/NIP. Admin Sarpras dan teknisi memakai akun yang sama
                    untuk menindaklanjuti laporan.
                </p>

                <ol
                    class="auth-brand-in relative mt-12 grid grid-cols-4"
                    aria-label="Tahap setiap laporan"
                >
                    <span
                        aria-hidden="true"
                        class="absolute top-[15px] right-[12.5%] left-[12.5%] h-0.5 bg-landing-line"
                    >
                        <span
                            class="auth-line block h-full origin-left bg-landing-gold"
                        />
                    </span>
                    <li
                        v-for="step in steps"
                        :key="step.label"
                        class="relative flex flex-col items-center text-center"
                    >
                        <span
                            class="auth-dot flex size-8 items-center justify-center rounded-full bg-landing-gold text-landing-gold-fg ring-4 ring-landing-bg"
                        >
                            <component
                                :is="step.icon"
                                class="size-4"
                                aria-hidden="true"
                            />
                        </span>
                        <span
                            class="mt-3 text-[13px] font-medium text-landing-fg"
                            >{{ step.label }}</span
                        >
                    </li>
                </ol>
            </div>

            <p class="auth-brand-in text-[13px] text-landing-muted">
                Bagian Sarana dan Prasarana, UIN Siber Syekh Nurjati Cirebon
            </p>
        </aside>

        <!-- Form column: form-first on every viewport. -->
        <div class="flex min-h-svh flex-col">
            <header
                class="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:justify-end"
            >
                <Link
                    :href="home()"
                    class="flex min-h-11 items-center gap-2.5 rounded-md focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:ring-offset-2 focus-visible:ring-offset-landing-bg focus-visible:outline-none lg:hidden"
                    aria-label="SiPerkasa, ke beranda"
                >
                    <span
                        class="flex size-9 items-center justify-center rounded-full bg-landing-fg"
                    >
                        <BrandLogo :size="20" />
                    </span>
                    <span
                        class="text-[17.5px] font-semibold tracking-tight text-landing-fg"
                        >SiPerkasa</span
                    >
                </Link>
                <Link
                    :href="home()"
                    class="inline-flex min-h-11 items-center gap-1.5 rounded-full px-3 text-[15px] font-medium text-landing-muted transition-colors duration-150 hover:bg-landing-surface hover:text-landing-fg focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:ring-offset-2 focus-visible:ring-offset-landing-bg focus-visible:outline-none active:bg-landing-line motion-reduce:transition-none"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Beranda
                </Link>
            </header>

            <main
                class="flex flex-1 justify-center px-4 pt-6 pb-10 sm:px-6 sm:pt-12 lg:items-center lg:pt-0"
            >
                <div
                    class="auth-card landing-paper w-full max-w-md self-start rounded-[22px] bg-card p-6 text-card-foreground shadow-[0_40px_80px_-24px_var(--color-landing-bg)] ring-1 ring-landing-line sm:p-8 lg:self-center"
                >
                    <div class="auth-head mb-8">
                        <h1
                            v-if="title"
                            class="text-[30px] leading-tight font-semibold tracking-[-0.02em] text-balance"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="mt-2 text-[15px] leading-relaxed text-pretty text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                    <div class="auth-body">
                        <slot />
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

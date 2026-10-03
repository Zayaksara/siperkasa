<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BrandLogo from '@/components/BrandLogo.vue';
import { useTemplateRef } from 'vue';
import { revealOnScroll, useMotion } from '@/lib/motion';
import { dashboard, login } from '@/routes';
import { scrollToSection } from './scrollTo';

defineProps<{ authed: boolean }>();

const year = new Date().getFullYear();

const cta = useTemplateRef<HTMLElement>('cta');

// Closing panel settles in (scale 0.98 to 1) once.
useMotion(cta, () => {
    revealOnScroll(cta.value ?? undefined, { y: 0, scale: 0.98 });
});
const nav = [
    { id: 'cara-kerja', label: 'Cara kerja' },
    { id: 'fitur', label: 'Fitur' },
    { id: 'peran', label: 'Peran' },
];
const focusRing =
    'focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:ring-offset-2 focus-visible:ring-offset-landing-bg focus-visible:outline-none';
</script>

<template>
    <section ref="cta" class="mt-24 sm:mt-32" aria-labelledby="cta-title">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div
                class="flex flex-col gap-8 rounded-[22px] bg-landing-surface p-6 ring-1 ring-landing-line sm:p-10 lg:flex-row lg:items-end lg:justify-between lg:p-14"
            >
                <div>
                    <h2
                        id="cta-title"
                        class="max-w-xl text-[30px] leading-tight font-semibold tracking-[-0.02em] text-balance text-landing-fg sm:text-[44px]"
                    >
                        Ada fasilitas yang rusak?
                    </h2>
                    <p
                        class="mt-4 max-w-lg text-base leading-relaxed text-landing-muted sm:text-[17.5px]"
                    >
                        Masuk dengan akun kampus Anda. Akun dibuat oleh Admin
                        Sarpras, jadi tidak perlu mendaftar.
                    </p>
                </div>
                <Link
                    :href="authed ? dashboard() : login()"
                    class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-full bg-landing-gold px-7 text-base font-semibold text-landing-gold-fg transition-colors duration-150 hover:bg-landing-gold-hover focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:ring-offset-2 focus-visible:ring-offset-landing-surface focus-visible:outline-none active:bg-landing-fg motion-reduce:transition-none"
                >
                    {{ authed ? 'Buka Dashboard' : 'Masuk untuk melapor' }}
                </Link>
            </div>
        </div>
    </section>

    <footer class="mt-16 border-t border-landing-line">
        <div
            class="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-10 sm:px-6 md:flex-row md:items-center md:justify-between"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-full bg-landing-fg"
                >
                    <BrandLogo :size="24" />
                </span>
                <div>
                    <p class="text-[15px] font-semibold text-landing-fg">
                        SiPerkasa
                    </p>
                    <p class="text-[13px] text-landing-muted">
                        Sistem Pelaporan Kerusakan Aset, UIN Siber Syekh Nurjati
                        Cirebon
                    </p>
                </div>
            </div>
            <nav aria-label="Navigasi footer">
                <ul class="flex flex-wrap gap-x-2">
                    <li v-for="item in nav" :key="item.id">
                        <a
                            :href="`#${item.id}`"
                            class="inline-flex min-h-11 items-center rounded-md px-2 text-[15px] text-landing-muted transition-colors duration-150 hover:text-landing-fg"
                            :class="focusRing"
                            @click="scrollToSection($event, item.id)"
                        >
                            {{ item.label }}
                        </a>
                    </li>
                    <li>
                        <Link
                            :href="authed ? dashboard() : login()"
                            class="inline-flex min-h-11 items-center rounded-md px-2 text-[15px] text-landing-muted transition-colors duration-150 hover:text-landing-fg"
                            :class="focusRing"
                        >
                            {{ authed ? 'Buka Dashboard' : 'Masuk' }}
                        </Link>
                    </li>
                </ul>
            </nav>
        </div>
        <p
            class="mx-auto max-w-6xl px-4 pb-8 text-[13px] text-landing-muted sm:px-6"
        >
            &copy; {{ year }} UIN Siber Syekh Nurjati Cirebon
        </p>
    </footer>
</template>

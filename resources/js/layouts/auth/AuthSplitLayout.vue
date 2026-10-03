<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, MapPin } from '@lucide/vue';
import BrandLogo from '@/components/BrandLogo.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const steps = ['Diterima', 'Divalidasi', 'Dikerjakan', 'Selesai'];
const doneCount = 3;
</script>

<template>
    <div
        class="min-h-svh overflow-x-clip bg-background font-sans text-foreground antialiased lg:grid lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] lg:gap-3 lg:p-3"
    >
        <!-- Brand panel: desktop only, decorative story of a report. -->
        <aside
            class="relative hidden overflow-hidden rounded-[22px] bg-sidebar p-10 text-sidebar-foreground lg:flex lg:flex-col lg:justify-between xl:p-12"
        >
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-sidebar-accent blur-3xl"
            />
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-32 -left-20 size-80 rounded-full bg-sidebar-accent/60 blur-3xl"
            />

            <Link
                :href="home()"
                class="relative flex min-h-11 items-center gap-3 self-start rounded-md focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:ring-offset-2 focus-visible:ring-offset-sidebar focus-visible:outline-none"
            >
                <BrandLogo :size="34" />
                <span class="flex flex-col">
                    <span
                        class="text-[21px] leading-tight font-bold tracking-tight text-sidebar-accent-foreground"
                        >SiPerkasa</span
                    >
                    <span class="text-[13px] text-sidebar-foreground/85"
                        >UIN Siber Syekh Nurjati Cirebon</span
                    >
                </span>
            </Link>

            <div class="relative max-w-md">
                <p
                    class="text-[30px] leading-tight font-bold tracking-tight text-balance text-sidebar-accent-foreground xl:text-[33px]"
                >
                    Satu akun untuk melapor dan memantau perbaikan aset kampus.
                </p>
                <p
                    class="mt-4 text-[15px] leading-relaxed text-pretty text-sidebar-foreground/85"
                >
                    Mahasiswa, dosen, dan tenaga kependidikan masuk dengan
                    NIM/NIP. Admin Sarpras dan teknisi memakai akun yang sama
                    untuk menindaklanjuti laporan.
                </p>

                <div
                    aria-hidden="true"
                    class="mt-8 rounded-2xl bg-card p-5 text-card-foreground shadow-[0_8px_28px_color-mix(in_oklab,var(--foreground)_10%,transparent)]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p
                                class="text-[13px] font-medium text-muted-foreground"
                            >
                                #LPR-0142
                            </p>
                            <p class="mt-1 text-base font-semibold">
                                Proyektor ruang kuliah mati
                            </p>
                            <p
                                class="mt-1 flex items-center gap-1.5 text-[13px] text-muted-foreground"
                            >
                                <MapPin class="size-3.5 shrink-0" />
                                <span class="truncate"
                                    >Gedung Kuliah Terpadu, Lt. 3</span
                                >
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full bg-status-in-progress-bg px-2.5 py-1 text-[13px] font-semibold text-status-in-progress"
                        >
                            Dikerjakan
                        </span>
                    </div>
                    <ol class="mt-5 grid grid-cols-4 gap-2">
                        <li v-for="(step, i) in steps" :key="step">
                            <span
                                class="block h-1.5 overflow-hidden rounded-full bg-muted"
                            >
                                <span
                                    v-if="i < doneCount"
                                    class="progress-fill block h-full origin-left rounded-full bg-primary"
                                    :style="{
                                        animationDelay: `${250 + i * 140}ms`,
                                    }"
                                />
                            </span>
                            <span
                                class="mt-2 block text-[13px]"
                                :class="
                                    i < doneCount
                                        ? 'font-medium text-foreground'
                                        : 'text-muted-foreground'
                                "
                                >{{ step }}</span
                            >
                        </li>
                    </ol>
                </div>
            </div>

            <p class="relative text-[13px] text-sidebar-foreground/85">
                Bagian Sarana dan Prasarana, UIN Siber Syekh Nurjati Cirebon
            </p>
        </aside>

        <!-- Form column: form-first on every viewport. -->
        <div class="relative isolate flex min-h-svh flex-col lg:min-h-0">
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-72 bg-[radial-gradient(ellipse_at_top,var(--secondary),transparent_70%)] lg:hidden"
            />

            <header
                class="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:justify-end"
            >
                <Link
                    :href="home()"
                    class="flex min-h-11 items-center gap-2.5 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none lg:hidden"
                    aria-label="SiPerkasa, ke beranda"
                >
                    <BrandLogo :size="28" />
                    <span
                        class="text-[17px] font-bold tracking-tight text-foreground"
                    >
                        Si<span class="text-primary">Perkasa</span>
                    </span>
                </Link>
                <Link
                    :href="home()"
                    class="inline-flex min-h-11 items-center gap-1.5 rounded-full px-3 text-[15px] font-medium text-muted-foreground transition-colors duration-150 hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:bg-secondary motion-reduce:transition-none"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Beranda
                </Link>
            </header>

            <main
                class="flex flex-1 justify-center px-4 pt-4 pb-10 sm:px-6 sm:pt-10 lg:items-center lg:pt-0"
            >
                <div class="form-enter w-full max-w-sm">
                    <div class="mb-8">
                        <h1
                            v-if="title"
                            class="text-[21px] leading-tight font-bold tracking-tight text-balance sm:text-[30px]"
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
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
/*
 * Motion is opacity/transform only and opt-in via prefers-reduced-motion.
 * The form fades in over 160ms with no delay, so inputs are usable at once.
 * The progress-bar fill is the single orchestrated moment and only lives in
 * the brand panel, which is hidden below lg (no extra work on phones).
 */
@media (prefers-reduced-motion: no-preference) {
    .form-enter {
        animation: auth-fade 160ms ease-out both;
    }

    .progress-fill {
        animation: auth-fill 700ms cubic-bezier(0.22, 1, 0.36, 1) both;
    }
}

@keyframes auth-fade {
    from {
        opacity: 0;
    }
}

@keyframes auth-fill {
    from {
        transform: scaleX(0);
    }
}
</style>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Camera,
    CircleCheck,
    Clock,
    MapPin,
    Smartphone,
    Wrench,
} from '@lucide/vue';
import { usePreferredReducedMotion, useWindowScroll } from '@vueuse/core';
import { computed } from 'vue';
import { dashboard, login } from '@/routes';
import Reveal from './Reveal.vue';
import { scrollToSection } from './scrollTo';

defineProps<{ authed: boolean }>();

const { y } = useWindowScroll();
const reduced = usePreferredReducedMotion();
const parallax = computed(() =>
    reduced.value === 'reduce'
        ? 'none'
        : `translateY(${Math.min(y.value, 600) * -0.06}px)`,
);

const timeline = [
    { label: 'Laporan diterima', time: '08.12', icon: Smartphone, done: true },
    {
        label: 'Divalidasi admin Sarpras',
        time: '08.40',
        icon: CircleCheck,
        done: true,
    },
    {
        label: 'Dikerjakan PIC teknisi',
        time: '10.05',
        icon: Wrench,
        done: true,
    },
    {
        label: 'Selesai, pelapor diberi notifikasi',
        time: '—',
        icon: Clock,
        done: false,
    },
];
</script>

<template>
    <section
        id="beranda"
        class="relative scroll-mt-24 overflow-hidden pt-28 pb-16 sm:pt-36 sm:pb-24"
        aria-labelledby="hero-title"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[520px] bg-[radial-gradient(ellipse_at_top,var(--secondary),transparent_70%)]"
        />
        <div class="mx-auto max-w-6xl px-4 text-center sm:px-6">
            <Reveal :duration="700">
                <p
                    class="inline-flex items-center gap-2 rounded-full border border-border bg-background px-3 py-1.5 text-[13px] font-medium text-muted-foreground"
                >
                    <span
                        class="size-1.5 rounded-full bg-brand-accent"
                        aria-hidden="true"
                    />
                    UIN Siber Syekh Nurjati Cirebon
                </p>
            </Reveal>
            <Reveal :duration="700" :delay="90">
                <h1
                    id="hero-title"
                    class="mx-auto mt-6 max-w-3xl text-[34px] leading-[1.1] font-bold tracking-tight text-balance text-foreground sm:text-5xl lg:text-[64px]"
                >
                    Lapor kerusakan aset kampus
                    <span class="text-primary">langsung dari HP.</span>
                </h1>
            </Reveal>
            <Reveal :duration="700" :delay="180">
                <p
                    class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-pretty text-muted-foreground sm:text-[17.5px]"
                >
                    Foto, kirim, lalu pantau prosesnya — dari validasi admin,
                    pengerjaan teknisi, sampai perbaikan selesai. Semua
                    transparan dalam satu sistem.
                </p>
            </Reveal>
            <Reveal
                :duration="700"
                :delay="270"
                class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center"
            >
                <Link
                    :href="authed ? dashboard() : login()"
                    class="group inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-primary px-7 text-base font-semibold text-primary-foreground transition-[background-color,translate,scale,box-shadow] duration-200 hover:-translate-y-0.5 hover:bg-primary/90 hover:shadow-[0_8px_28px_-6px_color-mix(in_oklab,var(--primary)_45%,transparent)] focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none active:translate-y-0 active:scale-[0.98] motion-reduce:transition-none motion-reduce:hover:translate-y-0"
                >
                    {{ authed ? 'Buka Dashboard' : 'Masuk untuk melapor' }}
                    <ArrowRight
                        class="size-4 transition-transform duration-200 group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </Link>
                <a
                    href="#cara-kerja"
                    class="inline-flex min-h-12 items-center justify-center rounded-full border border-border bg-background px-7 text-base font-semibold text-foreground transition-colors duration-200 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none active:bg-secondary"
                    @click="scrollToSection($event, 'cara-kerja')"
                >
                    Lihat cara kerja
                </a>
            </Reveal>
        </div>

        <Reveal
            :duration="900"
            :delay="380"
            from="scale"
            class="mx-auto mt-14 max-w-6xl px-4 sm:mt-20 sm:px-6"
        >
            <div
                class="relative overflow-hidden rounded-[22px] bg-sidebar p-4 text-sidebar-foreground shadow-[0_8px_28px_color-mix(in_oklab,var(--foreground)_10%,transparent)] sm:p-8 lg:p-12"
            >
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-sidebar-accent blur-3xl"
                />
                <div
                    class="relative grid items-center gap-6 lg:grid-cols-[1fr_1.1fr] lg:gap-12"
                    :style="{ transform: parallax }"
                >
                    <div class="hidden text-left lg:block">
                        <p
                            class="flex items-center gap-2 text-[13px] font-semibold tracking-[0.14em] text-sidebar-foreground uppercase"
                        >
                            <span
                                class="size-1.5 rounded-full bg-sidebar-primary"
                                aria-hidden="true"
                            />
                            Contoh laporan
                        </p>
                        <p
                            class="mt-3 text-[30px] leading-tight font-bold text-sidebar-accent-foreground"
                        >
                            Satu laporan, jejak lengkap dari awal sampai
                            selesai.
                        </p>
                        <p
                            class="mt-4 max-w-md text-[15px] leading-relaxed text-sidebar-foreground/85"
                        >
                            Setiap perubahan status tercatat dengan waktu dan
                            petugasnya, jadi pelapor tak perlu lagi bertanya ke
                            sana kemari.
                        </p>
                    </div>

                    <article
                        class="rounded-2xl bg-card p-4 text-left text-card-foreground sm:p-6"
                        aria-label="Ilustrasi kartu laporan kerusakan"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="text-[13px] font-medium text-muted-foreground"
                                >
                                    #LPR-0142
                                </p>
                                <p class="mt-1 text-[17.5px] font-semibold">
                                    Proyektor ruang kuliah mati
                                </p>
                                <p
                                    class="mt-1 flex items-center gap-1.5 text-[13px] text-muted-foreground"
                                >
                                    <MapPin
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate"
                                        >Gedung Kuliah Terpadu · Lt. 3</span
                                    >
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full bg-status-in-progress-bg px-2.5 py-1 text-[13px] font-semibold text-status-in-progress"
                            >
                                Dikerjakan
                            </span>
                        </div>
                        <div
                            class="mt-4 flex items-center gap-3 rounded-xl border border-dashed border-border bg-muted p-3 text-[13px] text-muted-foreground"
                        >
                            <Camera
                                class="size-5 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            2 foto bukti terlampir · Prioritas tinggi · SLA 2
                            hari
                        </div>
                        <ol class="mt-5 space-y-3">
                            <li
                                v-for="(step, i) in timeline"
                                :key="step.label"
                                class="flex items-center gap-3 text-[13px] sm:text-[15px]"
                            >
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full"
                                    :class="
                                        step.done
                                            ? 'bg-status-closed-bg text-status-closed'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <component
                                        :is="step.icon"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span
                                    class="min-w-0 flex-1"
                                    :class="
                                        step.done
                                            ? 'text-foreground'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ i + 1 }}. {{ step.label }}
                                </span>
                                <span
                                    class="shrink-0 text-muted-foreground tabular-nums"
                                    >{{ step.time }}</span
                                >
                            </li>
                        </ol>
                    </article>
                </div>
            </div>
        </Reveal>
    </section>
</template>

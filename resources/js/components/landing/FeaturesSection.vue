<script setup lang="ts">
import { Bell, Camera, FileSpreadsheet, Timer } from '@lucide/vue';
import Reveal from './Reveal.vue';

const features = [
    {
        icon: Camera,
        title: 'Foto bukti',
        text: 'Lampirkan foto kondisi aset saat melapor, dan PIC menambahkan foto setelah perbaikan selesai.',
        tag: 'Sebelum & sesudah',
        wide: true,
    },
    {
        icon: Timer,
        title: 'SLA & prioritas',
        text: 'Setiap laporan punya tingkat prioritas dan target waktu penyelesaian yang terukur.',
        tag: 'Tepat waktu',
        wide: false,
    },
    {
        icon: Bell,
        title: 'Notifikasi',
        text: 'Pelapor dan PIC mendapat kabar setiap kali status laporan berubah, tanpa perlu bertanya.',
        tag: 'Notifikasi di aplikasi',
        wide: false,
    },
    {
        icon: FileSpreadsheet,
        title: 'Dashboard & export',
        text: 'Ringkasan laporan per status, gedung, dan kategori, siap diekspor untuk rapat dan akuntabilitas.',
        tag: 'Siap rekap',
        wide: true,
    },
];
</script>

<template>
    <section
        id="fitur"
        class="scroll-mt-20 py-16 sm:py-24"
        aria-labelledby="fitur-title"
    >
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <Reveal class="max-w-2xl">
                <p
                    class="text-[13px] font-semibold tracking-[0.14em] text-brand-accent-strong uppercase"
                >
                    Fitur
                </p>
                <h2
                    id="fitur-title"
                    class="mt-3 text-[30px] leading-tight font-bold tracking-tight text-balance text-foreground sm:text-[40px]"
                >
                    Dibuat agar setiap perbaikan cepat, terukur, dan transparan.
                </h2>
            </Reveal>

            <div class="mt-10 grid gap-4 sm:mt-14 md:grid-cols-5">
                <Reveal
                    v-for="(f, i) in features"
                    :key="f.title"
                    as="article"
                    :delay="(i % 2) * 90"
                    class="group relative flex min-h-60 flex-col overflow-hidden rounded-[22px] border border-border bg-card p-6 transition-[translate,box-shadow] duration-300 hover:-translate-y-1 hover:shadow-[0_8px_28px_color-mix(in_oklab,var(--foreground)_8%,transparent)] motion-reduce:hover:translate-y-0 sm:p-8"
                    :class="f.wide ? 'md:col-span-3' : 'md:col-span-2'"
                >
                    <div
                        aria-hidden="true"
                        class="pointer-events-none absolute -right-10 -bottom-10 size-40 rounded-full bg-secondary transition-transform duration-300 group-hover:scale-125 motion-reduce:transition-none"
                    />
                    <div
                        class="relative flex items-start justify-between gap-3"
                    >
                        <span
                            class="flex size-12 items-center justify-center rounded-2xl bg-primary text-primary-foreground"
                        >
                            <component
                                :is="f.icon"
                                class="size-6"
                                aria-hidden="true"
                            />
                        </span>
                        <span
                            class="rounded-full border border-border bg-background px-3 py-1 text-[13px] font-medium text-muted-foreground"
                        >
                            {{ f.tag }}
                        </span>
                    </div>
                    <h3
                        class="relative mt-auto pt-10 text-[21px] font-semibold text-foreground"
                    >
                        {{ f.title }}
                    </h3>
                    <p
                        class="relative mt-2 max-w-md text-[15px] leading-relaxed text-muted-foreground"
                    >
                        {{ f.text }}
                    </p>
                </Reveal>
            </div>
        </div>
    </section>
</template>

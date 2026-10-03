<script setup lang="ts">
import { BadgeCheck, ClipboardCheck, Send, Wrench } from '@lucide/vue';
import Reveal from './Reveal.vue';

const steps = [
    {
        icon: Send,
        title: 'Lapor',
        text: 'Isi formulir singkat dari HP: pilih aset, jelaskan kerusakannya, lalu lampirkan foto dari kamera.',
    },
    {
        icon: ClipboardCheck,
        title: 'Validasi admin',
        text: 'Admin Sarpras memeriksa laporan, menentukan prioritas dan target waktu (SLA), lalu menugaskan PIC.',
    },
    {
        icon: Wrench,
        title: 'Dikerjakan PIC',
        text: 'Teknisi penanggung jawab memperbarui progres pengerjaan sehingga statusnya selalu terlihat.',
    },
    {
        icon: BadgeCheck,
        title: 'Selesai & notifikasi',
        text: 'Begitu perbaikan tuntas, laporan ditutup dan pelapor langsung menerima notifikasi.',
    },
];
</script>

<template>
    <section
        id="cara-kerja"
        class="scroll-mt-20 bg-muted py-16 sm:py-24"
        aria-labelledby="proses-title"
    >
        <div
            class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16"
        >
            <div class="lg:sticky lg:top-28 lg:self-start">
                <Reveal>
                    <p
                        class="text-[13px] font-semibold tracking-[0.14em] text-brand-accent-strong uppercase"
                    >
                        Cara kerja
                    </p>
                    <h2
                        id="proses-title"
                        class="mt-3 text-[30px] leading-tight font-bold tracking-tight text-balance text-foreground sm:text-[40px]"
                    >
                        Empat langkah dari laporan sampai beres.
                    </h2>
                    <p
                        class="mt-4 max-w-md text-base leading-relaxed text-muted-foreground"
                    >
                        Alur yang sama untuk semua laporan, sehingga tidak ada
                        yang tercecer dan setiap pihak tahu gilirannya.
                    </p>
                </Reveal>
            </div>

            <ol class="space-y-4">
                <Reveal
                    v-for="(step, i) in steps"
                    :key="step.title"
                    as="li"
                    :delay="i * 80"
                    class="group relative flex gap-4 rounded-2xl border border-border bg-card p-5 transition-[translate,box-shadow,border-color] duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-[0_8px_28px_color-mix(in_oklab,var(--foreground)_8%,transparent)] motion-reduce:hover:translate-y-0 sm:gap-5 sm:p-6"
                >
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary transition-colors duration-200 group-hover:bg-primary group-hover:text-primary-foreground"
                    >
                        <component
                            :is="step.icon"
                            class="size-5"
                            aria-hidden="true"
                        />
                    </span>
                    <div class="min-w-0">
                        <p
                            class="text-[13px] font-semibold text-muted-foreground tabular-nums"
                        >
                            {{ String(i + 1).padStart(2, '0') }}
                        </p>
                        <h3
                            class="mt-0.5 text-[17.5px] font-semibold text-foreground sm:text-[21px]"
                        >
                            {{ step.title }}
                        </h3>
                        <p
                            class="mt-1.5 text-[15px] leading-relaxed text-muted-foreground"
                        >
                            {{ step.text }}
                        </p>
                    </div>
                </Reveal>
            </ol>
        </div>
    </section>
</template>

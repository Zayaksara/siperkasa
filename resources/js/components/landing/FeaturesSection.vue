<script setup lang="ts">
import { Bell, Camera, FileSpreadsheet, History, Timer } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import { gsap, revealBatch, revealOnScroll, useMotion } from '@/lib/motion';

const features = [
    {
        icon: Camera,
        title: 'Foto sebelum dan sesudah',
        text: 'Pelapor melampirkan 1 sampai 3 foto kerusakan. Teknisi melampirkan foto hasil perbaikan saat menutup laporan.',
    },
    {
        icon: Timer,
        title: 'Prioritas dan target selesai',
        text: 'Admin Sarpras menetapkan prioritas, target selesai mengikuti prioritas itu, dan laporan yang melewati target ditandai.',
    },
    {
        icon: History,
        title: 'Riwayat status',
        text: 'Setiap perubahan status tercatat bersama waktu, pelaku, dan catatannya.',
    },
    {
        icon: Bell,
        title: 'Notifikasi di aplikasi',
        text: 'Pelapor dan teknisi mendapat kabar saat status laporan berubah.',
    },
    {
        icon: FileSpreadsheet,
        title: 'Rekap untuk Sarpras',
        text: 'Admin melihat ringkasan laporan di dashboard dan dapat mengekspor datanya.',
    },
];

const root = useTemplateRef<HTMLElement>('root');

// Heading settles, then rows arrive with a light stagger as they enter.
useMotion(root, ({ context }) => {
    const q = gsap.utils.selector(root.value);
    revealOnScroll(q('.ft-head')[0]);
    revealBatch(q('.ft-row'), context, { stagger: 0.1 });
});
</script>

<template>
    <section
        id="fitur"
        ref="root"
        class="scroll-mt-20 pt-24 sm:pt-32"
        aria-labelledby="fitur-title"
    >
        <div
            class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-[1fr_1.5fr] lg:gap-16"
        >
            <div class="ft-head">
                <h2
                    id="fitur-title"
                    class="max-w-md text-[30px] leading-tight font-semibold tracking-[-0.02em] text-balance text-landing-fg sm:text-[44px]"
                >
                    Setiap laporan punya bukti dan jejak.
                </h2>
                <p
                    class="mt-4 max-w-md text-base leading-relaxed text-landing-muted sm:text-[17.5px]"
                >
                    Semua yang terjadi pada sebuah laporan tersimpan di laporan
                    itu sendiri, bukan di grup pesan.
                </p>
            </div>

            <dl
                class="divide-y divide-landing-line border-y border-landing-line"
            >
                <div
                    v-for="f in features"
                    :key="f.title"
                    class="ft-row grid gap-2 py-6 sm:grid-cols-[15rem_1fr] sm:gap-8"
                >
                    <dt
                        class="flex items-start gap-3 text-[17.5px] font-semibold text-landing-fg"
                    >
                        <component
                            :is="f.icon"
                            class="mt-1 size-5 shrink-0 text-landing-gold"
                            aria-hidden="true"
                        />
                        {{ f.title }}
                    </dt>
                    <dd
                        class="pl-8 text-[15px] leading-relaxed text-landing-muted sm:pl-0"
                    >
                        {{ f.text }}
                    </dd>
                </div>
            </dl>
        </div>
    </section>
</template>

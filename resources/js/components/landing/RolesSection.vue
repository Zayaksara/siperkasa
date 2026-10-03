<script setup lang="ts">
import { useTemplateRef } from 'vue';
import { gsap, revealBatch, revealOnScroll, useMotion } from '@/lib/motion';

const roles = [
    {
        name: 'Pelapor',
        who: 'Mahasiswa, dosen, dan tenaga kependidikan',
        points: [
            'Melapor kerusakan dengan foto',
            'Mengubah atau membatalkan laporan selama belum divalidasi',
            'Memantau status dan menerima notifikasi',
        ],
    },
    {
        name: 'Admin Sarpras',
        who: 'Tim Sarana dan Prasarana',
        points: [
            'Memvalidasi atau menolak laporan',
            'Menetapkan prioritas, target selesai, dan PIC',
            'Melihat rekap dan mengekspor data',
        ],
    },
    {
        name: 'PIC teknisi',
        who: 'Teknisi penanggung jawab perbaikan',
        points: [
            'Melihat daftar tugas perbaikan',
            'Memperbarui status dan mencatat kendala',
            'Menutup laporan dengan foto hasil perbaikan',
        ],
    },
];

const root = useTemplateRef<HTMLElement>('root');

// Three columns enter together with a light left-to-right stagger.
useMotion(root, ({ context }) => {
    const q = gsap.utils.selector(root.value);
    revealOnScroll(q('.rl-head')[0]);
    revealBatch(q('.rl-col'), context, { stagger: 0.12 });
});
</script>

<template>
    <section
        id="peran"
        ref="root"
        class="scroll-mt-20 pt-24 sm:pt-32"
        aria-labelledby="peran-title"
    >
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <h2
                id="peran-title"
                class="rl-head max-w-2xl text-[30px] leading-tight font-semibold tracking-[-0.02em] text-balance text-landing-fg sm:text-[44px]"
            >
                Tiga peran, satu laporan yang sama.
            </h2>

            <ul class="mt-12 grid gap-10 md:grid-cols-3 md:gap-8 lg:mt-16">
                <li
                    v-for="role in roles"
                    :key="role.name"
                    class="rl-col border-t-2 border-landing-line pt-5"
                >
                    <h3 class="text-[21px] font-semibold text-landing-fg">
                        {{ role.name }}
                    </h3>
                    <p class="mt-1 text-[15px] text-landing-muted">
                        {{ role.who }}
                    </p>
                    <ul
                        class="mt-5 list-disc space-y-2 pl-5 text-[15px] leading-relaxed text-landing-fg marker:text-landing-gold"
                    >
                        <li v-for="point in role.points" :key="point">
                            {{ point }}
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </section>
</template>

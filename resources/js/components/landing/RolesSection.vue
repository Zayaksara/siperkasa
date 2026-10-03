<script setup lang="ts">
import { CircleCheck, HardHat, UserCog, UserRound } from '@lucide/vue';
import Reveal from './Reveal.vue';

const roles = [
    {
        icon: UserRound,
        name: 'Pelapor',
        who: 'Mahasiswa, dosen, dan tenaga kependidikan',
        points: [
            'Lapor kerusakan dengan foto',
            'Pantau status laporan',
            'Terima notifikasi saat selesai',
        ],
    },
    {
        icon: UserCog,
        name: 'Admin',
        who: 'Tim Sarana dan Prasarana',
        points: [
            'Validasi & tolak laporan',
            'Atur prioritas dan SLA',
            'Tugaskan PIC, rekap & export',
        ],
    },
    {
        icon: HardHat,
        name: 'PIC',
        who: 'Teknisi penanggung jawab',
        points: [
            'Lihat daftar tugas',
            'Perbarui progres pengerjaan',
            'Unggah foto hasil perbaikan',
        ],
    },
];
</script>

<template>
    <section
        id="peran"
        class="scroll-mt-20 bg-muted py-16 sm:py-24"
        aria-labelledby="peran-title"
    >
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <Reveal class="mx-auto max-w-2xl text-center">
                <p
                    class="text-[13px] font-semibold tracking-[0.14em] text-brand-accent-strong uppercase"
                >
                    Peran
                </p>
                <h2
                    id="peran-title"
                    class="mt-3 text-[30px] leading-tight font-bold tracking-tight text-balance text-foreground sm:text-[40px]"
                >
                    Satu sistem, tiga peran yang saling terhubung.
                </h2>
            </Reveal>

            <ul class="mt-10 grid gap-4 sm:mt-14 md:grid-cols-3">
                <Reveal
                    v-for="(role, i) in roles"
                    :key="role.name"
                    as="li"
                    :delay="i * 90"
                    class="group flex flex-col rounded-[22px] border border-border bg-card p-6 transition-[translate,box-shadow,border-color] duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-[0_8px_28px_color-mix(in_oklab,var(--foreground)_8%,transparent)] motion-reduce:hover:translate-y-0 sm:p-8"
                >
                    <span
                        class="flex size-12 items-center justify-center rounded-2xl bg-secondary text-primary transition-colors duration-200 group-hover:bg-primary group-hover:text-primary-foreground"
                    >
                        <component
                            :is="role.icon"
                            class="size-6"
                            aria-hidden="true"
                        />
                    </span>
                    <h3 class="mt-6 text-[21px] font-semibold text-foreground">
                        {{ role.name }}
                    </h3>
                    <p class="mt-1 text-[15px] text-muted-foreground">
                        {{ role.who }}
                    </p>
                    <ul class="mt-6 space-y-2.5 border-t border-border pt-6">
                        <li
                            v-for="point in role.points"
                            :key="point"
                            class="flex items-start gap-2.5 text-[15px] text-foreground"
                        >
                            <CircleCheck
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            {{ point }}
                        </li>
                    </ul>
                </Reveal>
            </ul>
        </div>
    </section>
</template>

<script setup lang="ts">
import {
    Bell,
    Check,
    CircleCheck,
    ClipboardCheck,
    ImageIcon,
    MapPin,
    Send,
    Wrench,
} from '@lucide/vue';
import { useTemplateRef } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';
import { motion, useMotion } from '@/lib/motion';

/**
 * Hero product mockup: one report travelling through the four stages.
 * Server-rendered markup is the finished state (Selesai), so it reads fully
 * without JS or with reduced motion. With motion, GSAP rewinds it to
 * "Dilaporkan", plays the load moment up to "Dikerjakan", then scroll
 * (scrubbed) carries it to "Selesai" and the reporter's notification.
 */
const props = withDefaults(defineProps<{ delay?: number }>(), { delay: 0 });

const steps = [
    { label: 'Dilaporkan', short: 'Lapor', time: '08.12', icon: Send },
    {
        label: 'Divalidasi',
        short: 'Validasi',
        time: '08.40',
        icon: ClipboardCheck,
    },
    { label: 'Dikerjakan', short: 'Dikerjakan', time: '10.05', icon: Wrench },
    { label: 'Selesai', short: 'Selesai', time: '13.20', icon: Check },
];

const log = [
    {
        status: 'Dilaporkan',
        time: '08.12',
        actor: 'Pelapor',
        note: 'Lampu proyektor berkedip lalu mati saat kuliah pagi.',
    },
    {
        status: 'Divalidasi',
        time: '08.40',
        actor: 'Admin Sarpras',
        note: 'Prioritas tinggi. Ditugaskan ke PIC teknisi.',
    },
    {
        status: 'Dikerjakan',
        time: '10.05',
        actor: 'PIC teknisi',
        note: 'Lampu proyektor diganti dengan unit cadangan.',
    },
    {
        status: 'Selesai',
        time: '13.20',
        actor: 'PIC teknisi',
        note: 'Proyektor menyala normal. Foto hasil perbaikan terlampir.',
    },
];

const details = [
    { label: 'Aset', value: 'Proyektor Epson EB-X500' },
    { label: 'Prioritas', value: 'Tinggi' },
    { label: 'Target selesai', value: '9 Okt 2026' },
    { label: 'PIC', value: 'PIC teknisi' },
];

const root = useTemplateRef<HTMLElement>('root');

useMotion(root, ({ gsap, context }) => {
    const q = gsap.utils.selector(root.value);
    const fill = q('.jm-fill');
    const dotsOff = q('.jm-dot-off');
    const rows = q('.jm-row');
    const progressBadge = q('.jm-badge-progress');
    const doneBadge = q('.jm-badge-done');
    const after = q('.jm-photo-after');
    const toast = q('.jm-toast');
    const bellBadge = q('.jm-bell-badge');

    // Rewind to "just reported".
    gsap.set(fill, { scaleX: 0, transformOrigin: 'left center' });
    gsap.set(dotsOff, { autoAlpha: 1 });
    gsap.set(rows, { autoAlpha: 0, y: 8 });
    gsap.set(doneBadge, { autoAlpha: 0 });
    gsap.set(progressBadge, { autoAlpha: 0 });
    gsap.set([...after, ...toast], { autoAlpha: 0, y: 12 });
    gsap.set(bellBadge, { autoAlpha: 0, scale: 0.4 });

    // Load moment: window rises, then the report walks to "Dikerjakan".
    gsap.timeline({ delay: props.delay, onComplete: carryOnScroll })
        .from(q('.jm-window'), {
            autoAlpha: 0,
            y: motion.rise,
            scale: 0.98,
            duration: 0.8,
        })
        .to(dotsOff[0], { autoAlpha: 0, duration: 0.25 }, '-=0.3')
        .to(rows[0], { autoAlpha: 1, y: 0, duration: 0.4 }, '<')
        .addLabel('validasi', '+=0.15')
        .to(
            fill,
            { scaleX: 1 / 3, duration: 0.5, ease: 'power2.inOut' },
            'validasi',
        )
        .to(dotsOff[1], { autoAlpha: 0, duration: 0.25 }, '>-0.1')
        .to(rows[1], { autoAlpha: 1, y: 0, duration: 0.4 }, '<')
        .addLabel('dikerjakan', '+=0.15')
        .to(
            fill,
            { scaleX: 2 / 3, duration: 0.5, ease: 'power2.inOut' },
            'dikerjakan',
        )
        .to(dotsOff[2], { autoAlpha: 0, duration: 0.25 }, '>-0.1')
        .to(rows[2], { autoAlpha: 1, y: 0, duration: 0.4 }, '<')
        .to(progressBadge, { autoAlpha: 1, duration: 0.3 }, '<');

    // Scroll carries it to "Selesai". Scrubbed, so scrolling back rewinds.
    // Created only once the load moment has finished, so the two never write
    // the same props at once: whatever the scroll position (reload, back
    // navigation) the scrub then takes over from "Dikerjakan" consistently.
    function carryOnScroll() {
        context.add(() => {
            gsap.timeline({
                defaults: { ease: 'none' },
                scrollTrigger: {
                    trigger: q('.jm-steps')[0],
                    start: 'top 60%',
                    end: 'top 25%',
                    scrub: 0.6,
                },
            })
                .fromTo(
                    fill,
                    { scaleX: 2 / 3 },
                    { scaleX: 1, duration: 1, immediateRender: false },
                )
                .to(dotsOff[3], { autoAlpha: 0, duration: 0.4 }, '>-0.2')
                .to(rows[3], { autoAlpha: 1, y: 0, duration: 0.6 }, '<')
                .fromTo(
                    progressBadge,
                    { autoAlpha: 1 },
                    { autoAlpha: 0, duration: 0.3, immediateRender: false },
                    '<',
                )
                .to(doneBadge, { autoAlpha: 1, duration: 0.3 }, '<')
                .to(after, { autoAlpha: 1, y: 0, duration: 0.6 }, '<')
                .to(
                    bellBadge,
                    { autoAlpha: 1, scale: 1, duration: 0.4 },
                    '>-0.1',
                )
                .to(toast, { autoAlpha: 1, y: 0, duration: 0.8 }, '<');
        });
    }
});
</script>

<template>
    <figure ref="root" class="relative" aria-labelledby="mockup-caption">
        <figcaption id="mockup-caption" class="sr-only">
            Contoh tampilan laporan di SiPerkasa. Laporan proyektor rusak
            berjalan dari dilaporkan, divalidasi, dikerjakan, sampai selesai,
            dan pelapor menerima notifikasi.
        </figcaption>

        <p class="mb-3 text-[13px] text-landing-muted" aria-hidden="true">
            Contoh tampilan
        </p>

        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-[8%] top-[20%] bottom-[-10%] rounded-[50%] bg-landing-glow opacity-50 blur-3xl"
        />

        <div
            class="jm-window landing-paper relative overflow-clip rounded-[22px] bg-muted text-foreground shadow-[0_40px_80px_-24px_var(--color-landing-bg)] ring-1 ring-landing-line"
        >
            <!-- App bar -->
            <div
                class="flex h-12 items-center justify-between gap-3 border-b border-border bg-card px-4 sm:h-14 sm:px-6"
            >
                <div class="flex min-w-0 items-center gap-2.5">
                    <BrandLogo :size="16" />
                    <span class="text-[13px] font-semibold sm:text-[15px]"
                        >SiPerkasa</span
                    >
                    <span
                        class="hidden truncate text-[13px] text-muted-foreground sm:inline"
                    >
                        / Laporan saya / SPK-2610-0142
                    </span>
                </div>
                <span class="relative text-muted-foreground">
                    <Bell class="size-5" aria-hidden="true" />
                    <span
                        aria-hidden="true"
                        class="jm-bell-badge absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-primary-foreground"
                    >
                        1
                    </span>
                    <span class="sr-only">1 notifikasi baru</span>
                </span>
            </div>

            <div class="p-4 sm:p-6 lg:p-8">
                <!-- Report header -->
                <div
                    class="flex flex-col-reverse items-start gap-3 sm:flex-row sm:justify-between"
                >
                    <div class="min-w-0">
                        <p
                            class="text-[13px] font-medium text-muted-foreground tabular-nums"
                        >
                            SPK-2610-0142
                        </p>
                        <p
                            class="mt-1 text-[21px] leading-tight font-semibold tracking-tight sm:text-[30px]"
                        >
                            Proyektor tidak menyala
                        </p>
                        <p
                            class="mt-2 flex items-start gap-1.5 text-[13px] text-muted-foreground sm:text-[15px]"
                        >
                            <MapPin
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            Gedung Kuliah Terpadu, lantai 3, ruang 301
                        </p>
                    </div>
                    <span class="inline-grid shrink-0">
                        <!-- Shown only during the animated sequence (JS). -->
                        <span
                            aria-hidden="true"
                            class="jm-badge-progress invisible inline-flex items-center gap-1.5 rounded-full bg-status-in-progress-bg px-3 py-1 text-[13px] font-semibold text-status-in-progress opacity-0 [grid-area:1/1]"
                        >
                            <Wrench class="size-3.5" aria-hidden="true" />
                            Dikerjakan
                        </span>
                        <span
                            class="jm-badge-done inline-flex items-center gap-1.5 rounded-full bg-status-closed-bg px-3 py-1 text-[13px] font-semibold text-status-closed [grid-area:1/1]"
                        >
                            <CircleCheck class="size-3.5" aria-hidden="true" />
                            Selesai
                        </span>
                    </span>
                </div>

                <!-- Stepper -->
                <ol
                    class="jm-steps relative mt-6 grid grid-cols-4 rounded-2xl border border-border bg-card px-0 py-4 sm:mt-8 sm:py-5"
                    aria-label="Tahap laporan"
                >
                    <span
                        aria-hidden="true"
                        class="absolute top-[31px] right-[12.5%] left-[12.5%] h-0.5 bg-border sm:top-[35px]"
                    >
                        <span
                            class="jm-fill block h-full origin-left bg-primary"
                        />
                    </span>
                    <li
                        v-for="step in steps"
                        :key="step.label"
                        class="relative flex flex-col items-center text-center"
                    >
                        <span
                            class="relative flex size-8 items-center justify-center rounded-full bg-primary text-primary-foreground ring-4 ring-card"
                        >
                            <component
                                :is="step.icon"
                                class="size-4"
                                aria-hidden="true"
                            />
                            <!-- "Not reached yet" cover, faded out by GSAP. -->
                            <span
                                aria-hidden="true"
                                class="jm-dot-off invisible absolute inset-0 rounded-full border-2 border-border bg-muted opacity-0"
                            />
                        </span>
                        <span class="mt-2 text-xs font-semibold sm:text-[13px]">
                            <!-- Short labels below sm so four fit at 320px -->
                            <span class="sm:hidden">{{ step.short }}</span>
                            <span class="hidden sm:inline">{{
                                step.label
                            }}</span>
                        </span>
                        <span
                            class="hidden text-[13px] text-muted-foreground tabular-nums sm:block"
                            >{{ step.time }}</span
                        >
                    </li>
                </ol>

                <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_1.2fr] lg:gap-6">
                    <!-- Details -->
                    <div
                        class="rounded-2xl border border-border bg-card p-4 sm:p-5"
                    >
                        <p class="text-[15px] font-semibold">Detail laporan</p>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div
                                class="flex aspect-[4/3] flex-col items-center justify-center gap-1 rounded-xl bg-status-in-progress-bg text-status-in-progress"
                            >
                                <ImageIcon class="size-5" aria-hidden="true" />
                                <span class="text-[13px] font-medium"
                                    >Foto sebelum</span
                                >
                            </div>
                            <div
                                class="jm-photo-after flex aspect-[4/3] flex-col items-center justify-center gap-1 rounded-xl bg-status-closed-bg text-status-closed"
                            >
                                <ImageIcon class="size-5" aria-hidden="true" />
                                <span class="text-[13px] font-medium"
                                    >Foto sesudah</span
                                >
                            </div>
                        </div>
                        <dl
                            class="mt-4 hidden divide-y divide-border text-[13px] sm:block"
                        >
                            <div
                                v-for="d in details"
                                :key="d.label"
                                class="flex justify-between gap-4 py-2 sm:text-[15px]"
                            >
                                <dt class="text-muted-foreground">
                                    {{ d.label }}
                                </dt>
                                <dd class="text-right font-medium">
                                    {{ d.value }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Status log -->
                    <div
                        class="rounded-2xl border border-border bg-card p-4 sm:p-5"
                    >
                        <p class="text-[15px] font-semibold">Riwayat status</p>
                        <ol class="mt-3">
                            <li
                                v-for="(entry, i) in log"
                                :key="entry.status"
                                class="jm-row relative flex gap-3 pb-4 last:pb-0"
                            >
                                <span
                                    v-if="i < log.length - 1"
                                    aria-hidden="true"
                                    class="absolute top-4 bottom-0 left-[5px] w-0.5 bg-border"
                                />
                                <span
                                    aria-hidden="true"
                                    class="relative mt-1.5 size-3 shrink-0 rounded-full"
                                    :class="
                                        i === log.length - 1
                                            ? 'bg-primary'
                                            : 'bg-secondary ring-2 ring-primary ring-inset'
                                    "
                                />
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="flex flex-wrap items-baseline justify-between gap-x-3 text-[13px] sm:text-[15px]"
                                    >
                                        <span class="font-semibold">{{
                                            entry.status
                                        }}</span>
                                        <span
                                            class="text-muted-foreground tabular-nums"
                                            >6 Okt, {{ entry.time }}</span
                                        >
                                    </p>
                                    <p
                                        class="text-[13px] text-muted-foreground"
                                    >
                                        {{ entry.actor }}
                                    </p>
                                    <p class="mt-1 text-[13px] leading-snug">
                                        {{ entry.note }}
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification the reporter receives -->
        <div
            class="jm-toast landing-paper relative mt-4 ml-auto flex w-full max-w-[22rem] items-start gap-3 rounded-2xl bg-card p-4 text-foreground shadow-[0_24px_48px_-16px_var(--color-landing-bg)] ring-1 ring-border sm:absolute sm:right-6 sm:-bottom-8 sm:mt-0 lg:-right-6"
        >
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-status-closed-bg text-status-closed"
            >
                <Bell class="size-5" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <p class="text-[15px] font-semibold">Laporan Anda selesai</p>
                <p
                    class="mt-0.5 text-[13px] leading-snug text-muted-foreground"
                >
                    Proyektor ruang 301 sudah diperbaiki. Lihat foto hasilnya di
                    SPK-2610-0142.
                </p>
            </div>
        </div>
    </figure>
</template>

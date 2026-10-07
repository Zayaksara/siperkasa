<script setup lang="ts">
import {
    Armchair,
    Building2,
    Camera,
    Ellipsis,
    MapPin,
    Monitor,
    PenLine,
    Plug,
    Speaker,
    Wifi,
} from '@lucide/vue';
import { useTemplateRef } from 'vue';
import { motion, useMotion } from '@/lib/motion';

/**
 * Hero guide: what to prepare before reporting, and what can be reported.
 * Deliberately a picture, not a form: no inputs, buttons, links or hover
 * states, so visitors never mistake it for the real report form.
 */
const props = withDefaults(defineProps<{ delay?: number }>(), { delay: 0 });

const prepare = [
    {
        icon: Camera,
        title: 'Foto kerusakan',
        text: 'Satu foto yang jelas, langsung dari HP.',
    },
    {
        icon: MapPin,
        title: 'Lokasi',
        text: 'Nama gedung dan ruangannya.',
    },
    {
        icon: PenLine,
        title: 'Keterangan singkat',
        text: 'Apa yang rusak dan sejak kapan.',
    },
];

const categories = [
    { icon: Plug, label: 'Elektronik' },
    { icon: Monitor, label: 'Komputer' },
    { icon: Wifi, label: 'Jaringan' },
    { icon: Speaker, label: 'Audio & Studio' },
    { icon: Armchair, label: 'Furnitur' },
    { icon: Building2, label: 'Bangunan & Sarana' },
];

const root = useTemplateRef<HTMLElement>('root');

useMotion(
    root,
    ({ gsap }) => {
        gsap.from(gsap.utils.selector(root.value)('.hg-in'), {
            autoAlpha: 0,
            y: motion.rise,
            duration: 0.5,
            stagger: motion.stagger,
            delay: props.delay,
            clearProps: 'transform,opacity,visibility',
        });
    },
    { hideUntilReady: true },
);
</script>

<template>
    <div
        ref="root"
        class="grid gap-10 rounded-3xl border border-landing-line bg-landing-surface p-6 sm:p-8 lg:grid-cols-[1.25fr_1fr] lg:gap-12 lg:p-10"
    >
        <section aria-labelledby="hg-prepare-title">
            <h2
                id="hg-prepare-title"
                class="hg-in text-xl font-semibold tracking-[-0.01em] text-landing-fg sm:text-2xl"
                data-reveal
            >
                Siapkan sebelum melapor
            </h2>
            <ol class="mt-6 grid gap-5">
                <li
                    v-for="(item, i) in prepare"
                    :key="item.title"
                    class="hg-in flex items-start gap-4"
                    data-reveal
                >
                    <span
                        class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-landing-glow text-landing-ring"
                        aria-hidden="true"
                    >
                        <component :is="item.icon" class="size-6" />
                    </span>
                    <div>
                        <p class="text-base font-semibold text-landing-fg">
                            <span class="text-landing-gold">{{ i + 1 }}.</span>
                            {{ item.title }}
                        </p>
                        <p
                            class="mt-1 text-[15px] leading-relaxed text-landing-muted"
                        >
                            {{ item.text }}
                        </p>
                    </div>
                </li>
            </ol>
        </section>

        <section aria-labelledby="hg-categories-title">
            <h2
                id="hg-categories-title"
                class="hg-in text-xl font-semibold tracking-[-0.01em] text-landing-fg sm:text-2xl"
                data-reveal
            >
                Yang bisa dilaporkan
            </h2>
            <ul class="mt-6 grid grid-cols-3 gap-x-3 gap-y-5">
                <li
                    v-for="cat in categories"
                    :key="cat.label"
                    class="hg-in flex flex-col items-center gap-2 text-center"
                    data-reveal
                >
                    <span
                        class="flex size-11 items-center justify-center rounded-xl bg-landing-glow text-landing-ring"
                        aria-hidden="true"
                    >
                        <component :is="cat.icon" class="size-5" />
                    </span>
                    <span
                        class="text-[13px] leading-tight text-landing-fg sm:text-sm"
                    >
                        {{ cat.label }}
                    </span>
                </li>
            </ul>
            <p
                class="hg-in mt-6 flex items-center gap-2 text-[15px] text-landing-muted"
                data-reveal
            >
                <Ellipsis class="size-5 text-landing-gold" aria-hidden="true" />
                dan kerusakan fasilitas kampus lainnya.
            </p>
        </section>
    </div>
</template>

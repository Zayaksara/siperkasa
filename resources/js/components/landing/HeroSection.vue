<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import { motion, useMotion } from '@/lib/motion';
import { dashboard, login } from '@/routes';
import HeroGuide from './HeroGuide.vue';
import { scrollToSection } from './scrollTo';

defineProps<{ authed: boolean }>();

const root = useTemplateRef<HTMLElement>('root');

// Load moment, part 1: copy settles in. The guide card (part 2) starts while
// this is still running, see HeroGuide's delay.
useMotion(
    root,
    ({ gsap, SplitText }) => {
        const host = root.value;
        const title = host?.querySelector<HTMLElement>('#hero-title');

        gsap.from(gsap.utils.selector(host)('.hero-in'), {
            autoAlpha: 0,
            y: motion.rise,
            duration: 0.7,
            stagger: motion.stagger,
        });

        if (!title || !SplitText) return;

        // Headline: characters rise from beneath a clip line with a calm
        // overshoot (back.out), once on load. Words stay whole, so lines
        // never break mid-word. autoSplit re-splits on resize and font load;
        // the returned tween keeps its progress across re-splits.
        SplitText.create(title, {
            type: 'lines,words,chars',
            mask: 'lines',
            autoSplit: true,
            aria: 'auto',
            linesClass: 'hero-line',
            onSplit: (self) =>
                gsap.from(self.chars, {
                    yPercent: 110,
                    duration: 0.8,
                    ease: 'back.out(1.7)',
                    stagger: 0.02,
                }),
        });
    },
    { hideUntilReady: true, plugins: ['SplitText'] },
);
</script>

<template>
    <section
        id="beranda"
        ref="root"
        class="relative scroll-mt-24 pt-28 pb-20 sm:pt-32 sm:pb-28"
        aria-labelledby="hero-title"
    >
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <p class="hero-in text-[15px] font-medium text-landing-muted">
                UIN Siber Syekh Nurjati Cirebon
            </p>
            <h1
                id="hero-title"
                data-reveal
                class="mt-4 max-w-5xl text-[40px] leading-[1.05] font-semibold tracking-[-0.03em] text-balance text-landing-fg sm:text-[56px] lg:text-[68px] lg:leading-[1]"
            >
                Laporkan kerusakan fasilitas kampus, pantau sampai selesai.
            </h1>
            <div
                class="mt-6 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between"
            >
                <p
                    class="hero-in max-w-[34rem] text-base leading-relaxed text-pretty text-landing-muted sm:text-[17.5px]"
                >
                    Foto kerusakannya dari HP dan kirim. Admin Sarpras
                    memvalidasi, teknisi mengerjakan, dan setiap perubahan
                    status tercatat di laporan Anda.
                </p>
                <div
                    class="hero-in flex shrink-0 flex-col items-stretch gap-3 min-[400px]:flex-row min-[400px]:items-center"
                >
                    <Link
                        :href="authed ? dashboard() : login()"
                        class="inline-flex min-h-12 items-center justify-center rounded-full bg-landing-gold px-7 text-base font-semibold text-landing-gold-fg transition-colors duration-150 hover:bg-landing-gold-hover focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:ring-offset-2 focus-visible:ring-offset-landing-bg focus-visible:outline-none active:bg-landing-fg motion-reduce:transition-none"
                    >
                        {{ authed ? 'Buka Dashboard' : 'Masuk untuk melapor' }}
                    </Link>
                    <a
                        href="#cara-kerja"
                        class="inline-flex min-h-12 items-center justify-center rounded-full border border-landing-muted/60 px-7 text-base font-semibold text-landing-fg transition-colors duration-150 hover:bg-landing-surface focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:ring-offset-2 focus-visible:ring-offset-landing-bg focus-visible:outline-none active:bg-landing-line motion-reduce:transition-none"
                        @click="scrollToSection($event, 'cara-kerja')"
                    >
                        Lihat cara kerja
                    </a>
                </div>
            </div>

            <HeroGuide class="mt-12 sm:mt-16" :delay="0.35" />
        </div>
    </section>
</template>

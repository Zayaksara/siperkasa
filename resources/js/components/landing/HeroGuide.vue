<script setup lang="ts">
import { ChevronDown, Pause, Play } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import tutorialPoster from '@/assets/videos/siperkasa-tutorial-poster.jpg';
import tutorialVideo from '@/assets/videos/siperkasa-tutorial.mp4';
import {
    motion,
    prefersReducedMotion,
    REDUCED_MOTION,
    useMotion,
} from '@/lib/motion';

/**
 * Hero guide: looping tutorial video of the reporter flow.
 * Not a form: no inputs, only a pause/play control (WCAG 2.2.2) and a
 * disclosure with the same steps as text.
 */
const props = withDefaults(defineProps<{ delay?: number }>(), { delay: 0 });

const root = useTemplateRef<HTMLElement>('root');
const video = useTemplateRef<HTMLVideoElement>('video');
const playing = ref(false);

let pausedByUser = false;
let observer: IntersectionObserver | undefined;
let reducedMotion: MediaQueryList | undefined;

function start(): void {
    video.value?.play().catch(() => {
        // Autoplay blocked: the play button stays available.
    });
}

function toggle(): void {
    const el = video.value;
    if (!el) return;
    if (el.paused) {
        pausedByUser = false;
        start();
    } else {
        pausedByUser = true;
        el.pause();
    }
}

function onReducedMotionChange(event: MediaQueryListEvent): void {
    if (event.matches) video.value?.pause();
}

// Only the observer starts playback: once a quarter of the video is on screen,
// and never again after the visitor paused it.
onMounted(() => {
    const el = video.value;
    if (!el) return;

    reducedMotion = window.matchMedia(REDUCED_MOTION);
    reducedMotion.addEventListener('change', onReducedMotionChange);

    observer = new IntersectionObserver(
        ([entry]) => {
            if (!entry) return;
            if (!entry.isIntersecting) {
                el.pause();
            } else if (!pausedByUser && !prefersReducedMotion()) {
                start();
            }
        },
        { threshold: 0.25 },
    );
    observer.observe(el);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    reducedMotion?.removeEventListener('change', onReducedMotionChange);
});

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
        class="rounded-3xl border border-landing-line bg-landing-surface p-3 sm:p-4"
    >
        <section aria-labelledby="hg-title">
            <div class="hg-in px-2 pt-2 pb-4 sm:px-3" data-reveal>
                <h2
                    id="hg-title"
                    class="text-xl font-semibold tracking-[-0.01em] text-landing-fg sm:text-2xl"
                >
                    Cara melapor di SiPerkasa
                </h2>
                <p class="mt-2 text-[15px] leading-relaxed text-landing-muted">
                    Tonton alurnya dalam satu menit: dari melapor sampai
                    perbaikan selesai.
                </p>
            </div>

            <div class="hg-in relative" data-reveal>
                <video
                    ref="video"
                    :src="tutorialVideo"
                    :poster="tutorialPoster"
                    muted
                    loop
                    playsinline
                    preload="metadata"
                    disablepictureinpicture
                    aria-label="Video tutorial cara melapor di SiPerkasa. Langkahnya bisa dibaca di bawah video."
                    class="aspect-video w-full rounded-2xl bg-landing-glow object-cover"
                    @play="playing = true"
                    @pause="playing = false"
                />
                <button
                    type="button"
                    class="absolute right-3 bottom-3 flex size-11 items-center justify-center rounded-full border border-landing-line bg-landing-surface/90 text-landing-fg focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    :aria-label="playing ? 'Jeda video' : 'Putar video'"
                    @click="toggle"
                >
                    <Pause v-if="playing" class="size-5" aria-hidden="true" />
                    <Play v-else class="size-5" aria-hidden="true" />
                </button>
            </div>

            <details class="hg-in group px-2 pt-3 pb-2 sm:px-3" data-reveal>
                <summary
                    class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg font-medium text-landing-fg focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:ring-offset-2 focus-visible:outline-none [&::-webkit-details-marker]:hidden"
                >
                    Baca langkahnya
                    <ChevronDown
                        class="size-5 text-landing-gold transition-transform group-open:rotate-180 motion-reduce:transition-none"
                        aria-hidden="true"
                    />
                </summary>
                <ol
                    class="mt-2 grid list-decimal gap-2 pl-5 text-[15px] leading-relaxed text-landing-muted marker:text-landing-gold"
                >
                    <li>Masuk dengan NIM atau NIP akun kampus.</li>
                    <li>
                        Tulis apa yang rusak, pilih kategori, gedung, dan
                        ruangnya.
                    </li>
                    <li>Lampirkan foto kerusakan dari HP.</li>
                    <li>Kirim, lalu simpan nomor tiketnya.</li>
                    <li>
                        Pantau statusnya: Menunggu (Admin Sarpras memeriksa),
                        Ditugaskan (PIC ditunjuk), Dikerjakan, sampai Selesai.
                        Setiap perubahan masuk ke notifikasi.
                    </li>
                </ol>
                <p class="mt-3 text-[15px] text-landing-fg">
                    Nemu fasilitas rusak? Lapor di SiPerkasa, pantau sampai
                    beres.
                </p>
            </details>
        </section>
    </div>
</template>

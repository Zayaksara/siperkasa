<script setup lang="ts">
import { Pause, Play } from '@lucide/vue';
import { computed, ref } from 'vue';
import CountUp from './CountUp.vue';
import Reveal from './Reveal.vue';

export interface LandingStats {
    total_reports: number;
    total_completed: number;
    total_in_progress: number;
    total_assets: number;
}

const props = defineProps<{ stats: LandingStats }>();

// WCAG 2.2.2: marquee pauses while keyboard focus is inside the block,
// and the button gives an explicit, persistent pause/play choice.
const paused = ref<boolean | null>(null);
const focused = ref(false);
const isPaused = computed(() => paused.value ?? focused.value);

// Only keyboard focus (:focus-visible) auto-pauses; a mouse/touch press on
// the button must not flip state before its own click handler runs.
function onFocusIn(event: FocusEvent): void {
    const el = event.target as HTMLElement | null;
    focused.value = el?.matches(':focus-visible') ?? false;
}

const items = computed(() => [
    {
        label: 'Laporan masuk',
        value: props.stats.total_reports,
        hint: 'Sejak sistem berjalan',
    },
    {
        label: 'Laporan selesai',
        value: props.stats.total_completed,
        hint: 'Sudah ditutup PIC',
    },
    {
        label: 'Sedang dikerjakan',
        value: props.stats.total_in_progress,
        hint: 'Dalam proses perbaikan',
    },
    {
        label: 'Aset terdaftar',
        value: props.stats.total_assets,
        hint: 'Tercatat di inventaris',
    },
]);

const assets = [
    'Proyektor',
    'AC ruangan',
    'Kursi & meja kuliah',
    'Jaringan Wi-Fi',
    'Komputer lab',
    'Lampu & listrik',
    'Toilet & air bersih',
    'Pintu & jendela',
    'Sound system',
    'Papan tulis',
];
</script>

<template>
    <section class="scroll-mt-24 py-16 sm:py-24" aria-labelledby="stats-title">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div
                class="grid gap-6 lg:grid-cols-[1fr_1.4fr] lg:items-end lg:gap-16"
            >
                <Reveal>
                    <p
                        class="text-[13px] font-semibold tracking-[0.14em] text-brand-accent-strong uppercase"
                    >
                        Angka terkini
                    </p>
                    <h2
                        id="stats-title"
                        class="mt-3 text-[30px] leading-tight font-bold tracking-tight text-balance text-foreground sm:text-[40px]"
                    >
                        Kerusakan kecil yang tak dilaporkan cepat jadi masalah
                        besar.
                    </h2>
                </Reveal>
                <Reveal :delay="80">
                    <p
                        class="text-base leading-relaxed text-muted-foreground sm:text-[17.5px]"
                    >
                        SiPerkasa memusatkan laporan dari seluruh civitas
                        akademika, sehingga tim Sarana dan Prasarana bisa
                        memprioritaskan, menugaskan, dan menuntaskan perbaikan
                        dengan data yang jelas.
                    </p>
                </Reveal>
            </div>

            <dl
                class="mt-10 grid grid-cols-2 gap-3 sm:mt-14 sm:gap-4 lg:grid-cols-4"
            >
                <Reveal
                    v-for="(item, i) in items"
                    :key="item.label"
                    :delay="i * 70"
                    class="group flex flex-col rounded-2xl border border-border bg-card p-4 transition-[translate,box-shadow] duration-300 hover:-translate-y-1 hover:shadow-[0_8px_28px_color-mix(in_oklab,var(--foreground)_8%,transparent)] motion-reduce:hover:translate-y-0 sm:p-6"
                >
                    <dt
                        class="order-2 mt-2 text-[13px] font-semibold text-foreground sm:text-[15px]"
                    >
                        {{ item.label }}
                    </dt>
                    <dd
                        class="order-1 text-[33px] leading-none font-bold tracking-tight text-primary sm:text-5xl"
                    >
                        <CountUp :value="item.value" />
                    </dd>
                    <dd class="order-3 mt-1 text-[13px] text-muted-foreground">
                        {{ item.hint }}
                    </dd>
                </Reveal>
            </dl>
        </div>

        <div
            class="mt-14 sm:mt-20"
            @focusin="onFocusIn"
            @focusout="focused = false"
        >
            <div
                class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 px-4"
            >
                <p
                    id="marquee-label"
                    class="text-[13px] font-medium text-muted-foreground"
                >
                    Mencakup seluruh jenis sarana kampus
                </p>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center gap-1.5 rounded-full px-3 text-[13px] font-semibold text-foreground transition-colors duration-150 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:bg-secondary motion-reduce:hidden"
                    @click="paused = !isPaused"
                >
                    <component
                        :is="isPaused ? Play : Pause"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{
                        isPaused
                            ? 'Jalankan daftar sarana'
                            : 'Jeda daftar sarana'
                    }}
                </button>
            </div>
            <div
                class="landing-marquee relative mt-4 flex overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_12%,black_88%,transparent)]"
                :class="{ 'is-paused': isPaused }"
                role="group"
                aria-labelledby="marquee-label"
            >
                <ul
                    v-for="copy in 2"
                    :key="copy"
                    class="landing-marquee-track flex shrink-0 items-center gap-3 pr-3"
                    :aria-hidden="copy === 2 ? 'true' : undefined"
                >
                    <li
                        v-for="asset in assets"
                        :key="asset"
                        class="rounded-full border border-border bg-muted px-4 py-2 text-[15px] font-medium whitespace-nowrap text-foreground"
                    >
                        {{ asset }}
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>

<style scoped>
.landing-marquee-track {
    animation: landing-marquee 38s linear infinite;
}
.landing-marquee:hover .landing-marquee-track,
.landing-marquee.is-paused .landing-marquee-track {
    animation-play-state: paused;
}
@keyframes landing-marquee {
    to {
        transform: translateX(-100%);
    }
}
@media (prefers-reduced-motion: reduce) {
    .landing-marquee {
        mask-image: none;
        flex-wrap: wrap;
        justify-content: center;
        padding-inline: 1rem;
    }
    .landing-marquee-track {
        animation: none;
        flex-wrap: wrap;
        justify-content: center;
        flex-shrink: 1;
        row-gap: 0.75rem;
    }
    .landing-marquee-track[aria-hidden='true'] {
        display: none;
    }
}
</style>

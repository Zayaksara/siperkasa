<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, useTemplateRef } from 'vue';
import ErrorIllustration from '@/components/ErrorIllustration.vue';
import { motion, useMotion } from '@/lib/motion';
import { dashboard, home } from '@/routes';

const props = defineProps<{ status: number }>();

type Action = 'home' | 'back' | 'reload';

interface Copy {
    title: string;
    text: string;
    actions: Action[];
    help?: boolean;
}

const COPY: Record<number, Copy> = {
    403: {
        title: 'Akses ditolak',
        text: 'Akun Anda tidak memiliki izin untuk membuka halaman ini.',
        actions: ['home', 'back'],
        help: true,
    },
    404: {
        title: 'Halaman tidak ditemukan',
        text: 'Alamat mungkin salah ketik atau halaman sudah dipindahkan.',
        actions: ['home', 'back'],
    },
    419: {
        title: 'Sesi telah berakhir',
        text: 'Halaman terlalu lama dibiarkan. Muat ulang lalu coba lagi.',
        actions: ['reload', 'home'],
    },
    429: {
        title: 'Terlalu banyak percobaan',
        text: 'Tunggu sekitar satu menit sebelum mencoba lagi.',
        actions: ['reload', 'home'],
    },
    500: {
        title: 'Terjadi kesalahan sistem',
        text: 'Ini bukan kesalahan Anda. Coba lagi beberapa saat lagi.',
        actions: ['reload', 'home'],
        help: true,
    },
    503: {
        title: 'Sedang pemeliharaan',
        text: 'SiPerkasa sedang diperbarui dan akan segera kembali.',
        actions: ['reload'],
        help: true,
    },
};

const FALLBACK: Copy = {
    title: 'Terjadi kesalahan',
    text: 'Coba lagi beberapa saat lagi.',
    actions: ['home', 'back'],
};

const copy = computed(() => COPY[props.status] ?? FALLBACK);

// Shared props can be missing on unmatched routes, so read them defensively.
const page = usePage();
const homeHref = computed(() =>
    (page.props as { auth?: { user?: unknown } }).auth?.user
        ? dashboard()
        : home(),
);

const reloadLabel = computed(() =>
    props.status === 419 || props.status === 503 ? 'Muat ulang' : 'Coba lagi',
);

// "Kembali" only when there is somewhere to go back to (client only).
const canGoBack = ref(false);
onMounted(() => {
    canGoBack.value = window.history.length > 1;
});

const actions = computed(() =>
    copy.value.actions.filter((a) => a !== 'back' || canGoBack.value),
);

const btnPrimary =
    'inline-flex min-h-12 w-full items-center justify-center rounded-full bg-landing-gold px-7 text-base font-semibold text-landing-gold-fg transition-colors duration-150 hover:bg-landing-gold-hover focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:ring-offset-2 focus-visible:ring-offset-landing-bg focus-visible:outline-none active:bg-landing-fg motion-reduce:transition-none sm:w-auto';
const btnSecondary =
    'inline-flex min-h-12 w-full items-center justify-center rounded-full border border-landing-muted bg-transparent px-7 text-base font-semibold text-landing-fg transition-colors duration-150 hover:bg-landing-surface focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:ring-offset-2 focus-visible:ring-offset-landing-bg focus-visible:outline-none motion-reduce:transition-none sm:w-auto';

function reload() {
    window.location.reload();
}

function back() {
    window.history.back();
}

const root = useTemplateRef<HTMLElement>('root');

// One short entrance: picture slides in from the left, text from the right.
// Reduced motion skips setup entirely, so everything stays in its final state.
useMotion(
    root,
    ({ gsap }) => {
        const q = gsap.utils.selector(root.value);
        const head = q('.er-head');
        const left = q('.er-left');
        const right = q('.er-right');
        // Apply the start state synchronously (set renders at once) so the
        // pending-hide attribute can be lifted with no visible frame.
        gsap.set([head, left, right], { autoAlpha: 0 });
        gsap.set(left, { x: -motion.rise });
        gsap.set(right, { x: motion.rise });
        const end = {
            autoAlpha: 1,
            x: 0,
            duration: 0.45,
            clearProps: 'transform,opacity,visibility',
        };
        gsap.to(head, {
            autoAlpha: 1,
            duration: 0.3,
            clearProps: 'opacity,visibility',
        });
        gsap.to(left, end);
        gsap.to(right, { ...end, delay: 0.08 });
    },
    { hideUntilReady: true },
);
</script>

<template>
    <Head :title="`${status} · ${copy.title}`" />
    <main
        ref="root"
        class="flex min-h-svh flex-col bg-landing-bg text-landing-fg"
    >
        <header data-reveal class="er-head px-6 py-5 sm:px-10 lg:px-16">
            <Link
                :href="homeHref"
                class="inline-flex min-h-11 items-center rounded-sm text-lg font-bold tracking-[-0.01em] text-landing-ring focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:ring-offset-2 focus-visible:ring-offset-landing-bg focus-visible:outline-none"
            >
                SiPerkasa
            </Link>
        </header>

        <div
            class="mx-auto grid w-full max-w-6xl flex-1 items-center gap-8 px-6 pt-4 pb-10 sm:px-10 lg:grid-cols-2 lg:gap-16 lg:px-16"
        >
            <ErrorIllustration
                :status="status"
                data-reveal
                class="er-left mx-auto max-w-60 sm:max-w-80 lg:max-w-[520px]"
            />

            <section
                data-reveal
                class="er-right mx-auto flex w-full max-w-md flex-col items-center text-center"
                aria-labelledby="err-title"
            >
                <p
                    class="text-5xl font-bold tracking-tight text-landing-ring tabular-nums sm:text-6xl"
                >
                    {{ status }}
                </p>
                <h1
                    id="err-title"
                    class="mt-3 text-3xl font-semibold tracking-[-0.02em] text-landing-fg sm:text-4xl"
                >
                    {{ copy.title }}
                </h1>
                <p class="mt-3 text-lg leading-7 text-landing-muted">
                    {{ copy.text }}
                </p>

                <div
                    class="mt-8 flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:justify-center"
                >
                    <template v-for="(action, i) in actions" :key="action">
                        <Link
                            v-if="action === 'home'"
                            :href="homeHref"
                            :class="i === 0 ? btnPrimary : btnSecondary"
                        >
                            Ke beranda
                        </Link>
                        <button
                            v-else-if="action === 'reload'"
                            type="button"
                            :class="i === 0 ? btnPrimary : btnSecondary"
                            @click="reload"
                        >
                            {{ reloadLabel }}
                        </button>
                        <button
                            v-else
                            type="button"
                            :class="i === 0 ? btnPrimary : btnSecondary"
                            @click="back"
                        >
                            Kembali
                        </button>
                    </template>
                </div>

                <p
                    v-if="copy.help"
                    class="mt-6 text-sm leading-5 text-landing-muted"
                >
                    Butuh bantuan? Hubungi PUSTIKOM UIN Siber Syekh Nurjati
                    Cirebon.
                </p>

                <p class="mt-8 text-sm text-landing-muted">
                    Ilustrasi:
                    <a
                        href="https://www.getillustrations.com"
                        target="_blank"
                        rel="noopener"
                        class="rounded-sm font-medium text-landing-muted underline underline-offset-2 hover:text-landing-fg focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:outline-none"
                        >getillustrations.com</a
                    >
                </p>
            </section>
        </div>
    </main>
</template>

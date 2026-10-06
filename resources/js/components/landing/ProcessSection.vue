<script setup lang="ts">
import { computed, useTemplateRef } from 'vue';
import { revealOnScroll, useMotion } from '@/lib/motion';

const props = defineProps<{ totalAssets: number }>();

const fmt = new Intl.NumberFormat('id-ID');

// A zero count says nothing useful, so the figure only appears when > 0.
const pickAsset = computed(() =>
    Number(props.totalAssets) > 0
        ? `Pilih dari ${fmt.format(props.totalAssets)} aset yang sudah tercatat`
        : 'Pilih aset dan lokasinya',
);

const steps = computed(() => [
    {
        title: 'Lapor',
        who: 'Pelapor',
        text: `${pickAsset.value}, jelaskan kerusakannya, lalu lampirkan foto dari kamera HP.`,
    },
    {
        title: 'Validasi',
        who: 'Admin Sarpras',
        text: 'Laporan diperiksa, diberi prioritas dan target selesai, lalu ditugaskan ke teknisi. Laporan yang tidak sesuai ditolak dengan alasan.',
    },
    {
        title: 'Dikerjakan',
        who: 'PIC teknisi',
        text: 'Teknisi menandai laporan sedang dikerjakan dan mencatat kendala bila perbaikan harus ditunda.',
    },
    {
        title: 'Selesai',
        who: 'PIC teknisi',
        text: 'Teknisi menutup laporan dengan foto hasil perbaikan, dan pelapor menerima notifikasi.',
    },
]);

const root = useTemplateRef<HTMLElement>('root');

// Step text is always fully readable (no scroll-bound visibility, so a jump
// via the nav lands on legible copy). Scroll only scrubs the decorative gold
// bars across, in order, both ways.
useMotion(root, (kit) => {
    const { gsap, isDesktop } = kit;
    const q = gsap.utils.selector(root.value);
    const bars = q('.ps-bar');

    revealOnScroll(kit, q('.ps-head')[0]);

    if (isDesktop) {
        const tl = gsap.timeline({
            scrollTrigger: {
                trigger: q('.ps-steps')[0],
                start: 'top 85%',
                end: 'top 45%',
                scrub: 0.6,
            },
        });
        bars.forEach((bar, i) => {
            tl.fromTo(
                bar,
                { scaleX: 0 },
                { scaleX: 1, duration: 1, ease: 'none' },
                i === 0 ? 0 : '>-0.2',
            );
        });
        return;
    }

    bars.forEach((bar) => {
        gsap.fromTo(
            bar,
            { scaleX: 0 },
            {
                scaleX: 1,
                ease: 'none',
                scrollTrigger: {
                    trigger: bar.parentElement,
                    start: 'top 90%',
                    end: 'top 60%',
                    scrub: 0.6,
                },
            },
        );
    });
});
</script>

<template>
    <section
        id="cara-kerja"
        ref="root"
        class="scroll-mt-20 pt-12 sm:pt-16 lg:pt-14"
        aria-labelledby="proses-title"
    >
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="ps-head">
                <h2
                    id="proses-title"
                    class="max-w-2xl text-[30px] leading-tight font-semibold tracking-[-0.02em] text-balance text-landing-fg sm:text-[44px]"
                >
                    Empat tahap untuk setiap laporan.
                </h2>
                <p
                    class="mt-4 max-w-xl text-base leading-relaxed text-landing-muted sm:text-[17.5px]"
                >
                    Semua laporan melewati tahap yang sama, jadi Anda selalu
                    tahu siapa yang sedang memegangnya.
                </p>
            </div>

            <div class="ps-steps mt-12 lg:mt-16">
                <ol class="grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                    <li
                        v-for="(step, i) in steps"
                        :key="step.title"
                        class="relative border-t-2 border-landing-line pt-5"
                    >
                        <!-- Gold bar = step reached; drawn by GSAP on scroll. -->
                        <span
                            aria-hidden="true"
                            class="ps-bar absolute -top-0.5 left-0 h-0.5 w-full origin-left bg-landing-gold"
                        />
                        <div class="ps-body">
                            <p class="flex items-baseline gap-3">
                                <span
                                    class="text-[15px] font-semibold text-landing-gold tabular-nums"
                                    >{{ i + 1 }}</span
                                >
                                <span
                                    class="text-[21px] font-semibold text-landing-fg"
                                    >{{ step.title }}</span
                                >
                            </p>
                            <p
                                class="mt-1 text-[15px] font-medium text-landing-fg/90"
                            >
                                {{ step.who }}
                            </p>
                            <p
                                class="mt-3 text-[15px] leading-relaxed text-landing-muted"
                            >
                                {{ step.text }}
                            </p>
                        </div>
                    </li>
                </ol>
            </div>
        </div>
    </section>
</template>

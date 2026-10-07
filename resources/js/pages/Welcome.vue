<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FeaturesSection from '@/components/landing/FeaturesSection.vue';
import HeroSection from '@/components/landing/HeroSection.vue';
import LandingFooter from '@/components/landing/LandingFooter.vue';
import LandingHeader from '@/components/landing/LandingHeader.vue';
import ProcessSection from '@/components/landing/ProcessSection.vue';
import RolesSection from '@/components/landing/RolesSection.vue';
import type { LandingStats } from '@/components/landing/types';

defineProps<{ stats: LandingStats }>();

const page = usePage();
const authed = computed(() => Boolean(page.props.auth?.user));
</script>

<template>
    <Head title="Lapor Kerusakan Aset Kampus" />
    <div
        class="min-h-screen overflow-x-clip bg-landing-bg font-sans text-landing-fg antialiased [color-scheme:light]"
    >
        <a
            href="#konten"
            class="sr-only z-[60] rounded-full bg-landing-gold px-5 py-3 font-semibold text-landing-gold-fg focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:ring-offset-2 focus-visible:ring-offset-landing-bg focus-visible:outline-none"
        >
            Lewati ke konten utama
        </a>

        <LandingHeader :authed="authed" />

        <main id="konten" tabindex="-1" class="focus:outline-none">
            <HeroSection :authed="authed" />
            <ProcessSection :total-assets="stats.total_assets" />
            <FeaturesSection />
            <RolesSection />
        </main>

        <LandingFooter />
    </div>
</template>

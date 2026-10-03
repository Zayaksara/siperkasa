<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import BrandLogo from '@/components/BrandLogo.vue';
import { dashboard, login } from '@/routes';
import Reveal from './Reveal.vue';
import { scrollToSection } from './scrollTo';

defineProps<{ authed: boolean }>();

const year = new Date().getFullYear();
const nav = [
    { id: 'cara-kerja', label: 'Cara kerja' },
    { id: 'fitur', label: 'Fitur' },
    { id: 'peran', label: 'Peran' },
];
const linkClass =
    'inline-flex min-h-11 items-center rounded-md text-[15px] text-muted-foreground transition-colors duration-150 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <section class="px-4 py-16 sm:px-6 sm:py-24" aria-labelledby="cta-title">
        <Reveal
            from="scale"
            :duration="500"
            class="relative mx-auto max-w-6xl overflow-hidden rounded-[22px] bg-sidebar px-6 py-14 text-center text-sidebar-foreground sm:px-12 sm:py-20"
        >
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-32 left-1/2 size-96 -translate-x-1/2 rounded-full bg-sidebar-accent blur-3xl"
            />
            <div class="relative">
                <h2
                    id="cta-title"
                    class="mx-auto max-w-2xl text-[30px] leading-tight font-bold tracking-tight text-balance text-sidebar-accent-foreground sm:text-[44px]"
                >
                    Ada fasilitas rusak? Laporkan sekarang, kami pantau sampai
                    selesai.
                </h2>
                <p
                    class="mx-auto mt-4 max-w-lg text-base text-sidebar-foreground"
                >
                    Gunakan akun kampus Anda untuk masuk. Akun dibuat oleh admin
                    Sarpras.
                </p>
                <Link
                    :href="authed ? dashboard() : login()"
                    class="group mt-8 inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-sidebar-accent-foreground px-7 text-base font-semibold text-sidebar transition-[translate,scale,background-color] duration-200 hover:-translate-y-0.5 hover:bg-sidebar-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:ring-offset-2 focus-visible:ring-offset-sidebar focus-visible:outline-none active:translate-y-0 active:scale-[0.98] motion-reduce:transition-none motion-reduce:hover:translate-y-0"
                >
                    {{ authed ? 'Buka Dashboard' : 'Masuk ke SiPerkasa' }}
                    <ArrowUpRight
                        class="size-4 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                        aria-hidden="true"
                    />
                </Link>
            </div>
        </Reveal>
    </section>

    <footer class="border-t border-border">
        <div
            class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:grid-cols-[1.6fr_1fr_1fr] sm:px-6"
        >
            <div>
                <div class="flex items-center gap-3">
                    <BrandLogo :size="36" />
                    <div>
                        <p class="text-[17.5px] font-bold text-foreground">
                            SiPerkasa
                        </p>
                        <p class="text-[13px] text-muted-foreground">
                            Sistem Pelaporan Kerusakan Aset
                        </p>
                    </div>
                </div>
                <p
                    class="mt-4 max-w-sm text-[15px] leading-relaxed text-muted-foreground"
                >
                    Universitas Islam Negeri Siber Syekh Nurjati Cirebon
                </p>
            </div>
            <nav aria-label="Navigasi footer">
                <p class="text-[13px] font-semibold text-foreground">Halaman</p>
                <ul class="mt-2">
                    <li v-for="item in nav" :key="item.id">
                        <a
                            :href="`#${item.id}`"
                            :class="linkClass"
                            @click="scrollToSection($event, item.id)"
                        >
                            {{ item.label }}
                        </a>
                    </li>
                </ul>
            </nav>
            <div>
                <p class="text-[13px] font-semibold text-foreground">Akses</p>
                <ul class="mt-2">
                    <li>
                        <Link
                            :href="authed ? dashboard() : login()"
                            :class="linkClass"
                        >
                            {{ authed ? 'Buka Dashboard' : 'Masuk' }}
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
        <div class="border-t border-border">
            <p
                class="mx-auto max-w-6xl px-4 py-6 text-[13px] text-muted-foreground sm:px-6"
            >
                &copy; {{ year }} SiPerkasa · UIN Siber Syekh Nurjati Cirebon.
                Hak cipta dilindungi.
            </p>
        </div>
    </footer>
</template>

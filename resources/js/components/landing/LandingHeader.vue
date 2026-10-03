<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Menu, X } from '@lucide/vue';
import { onClickOutside, onKeyStroke, useWindowScroll } from '@vueuse/core';
import { computed, ref, useTemplateRef } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';
import { dashboard, login } from '@/routes';
import { scrollToSection } from './scrollTo';

defineProps<{ authed: boolean }>();

const { y } = useWindowScroll();
const menuOpen = ref(false);
const menuButton = useTemplateRef<HTMLButtonElement>('menuButton');
const headerEl = useTemplateRef<HTMLElement>('headerEl');
const solid = computed(() => y.value > 24 || menuOpen.value);

onKeyStroke('Escape', () => {
    if (!menuOpen.value) return;
    menuOpen.value = false;
    menuButton.value?.focus();
});

// Tap or click anywhere outside the header closes the mobile menu.
onClickOutside(headerEl, () => {
    menuOpen.value = false;
});

function go(event: MouseEvent, id: string): void {
    menuOpen.value = false;
    scrollToSection(event, id);
}

const links = [
    { id: 'cara-kerja', label: 'Cara kerja' },
    { id: 'fitur', label: 'Fitur' },
    { id: 'peran', label: 'Peran' },
];

const focusRing =
    'focus-visible:ring-2 focus-visible:ring-landing-ring focus-visible:ring-offset-2 focus-visible:ring-offset-landing-bg focus-visible:outline-none';
</script>

<template>
    <header
        ref="headerEl"
        class="fixed inset-x-0 top-0 z-50 border-b transition-[background-color,border-color] duration-300 motion-reduce:transition-none"
        :class="
            solid
                ? 'border-landing-line bg-landing-bg/90 backdrop-blur-md'
                : 'border-transparent bg-transparent'
        "
    >
        <div
            class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6"
        >
            <a
                href="#beranda"
                class="flex min-h-11 items-center gap-2.5 rounded-md"
                :class="focusRing"
                aria-label="SiPerkasa, kembali ke atas"
                @click="scrollToSection($event, 'beranda')"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-full bg-landing-fg"
                >
                    <BrandLogo :size="20" />
                </span>
                <span
                    class="hidden text-[17.5px] font-semibold tracking-tight text-landing-fg min-[400px]:inline"
                >
                    SiPerkasa
                </span>
            </a>

            <nav aria-label="Navigasi halaman" class="hidden md:block">
                <ul class="flex items-center gap-1">
                    <li v-for="link in links" :key="link.id">
                        <a
                            :href="`#${link.id}`"
                            class="inline-flex min-h-11 items-center rounded-full px-4 text-[15px] font-medium text-landing-muted transition-colors duration-150 hover:bg-landing-surface hover:text-landing-fg active:bg-landing-line"
                            :class="focusRing"
                            @click="scrollToSection($event, link.id)"
                        >
                            {{ link.label }}
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="flex items-center gap-2">
                <button
                    ref="menuButton"
                    type="button"
                    class="inline-flex size-11 items-center justify-center rounded-full text-landing-fg transition-colors duration-150 hover:bg-landing-surface active:bg-landing-line md:hidden"
                    :class="focusRing"
                    :aria-expanded="menuOpen"
                    aria-controls="menu-seluler"
                    aria-label="Menu navigasi"
                    @click="menuOpen = !menuOpen"
                >
                    <component
                        :is="menuOpen ? X : Menu"
                        class="size-5"
                        aria-hidden="true"
                    />
                </button>
                <Link
                    :href="authed ? dashboard() : login()"
                    class="inline-flex min-h-11 items-center rounded-full bg-landing-gold px-5 text-[15px] font-semibold text-landing-gold-fg transition-colors duration-150 hover:bg-landing-gold-hover active:bg-landing-fg motion-reduce:transition-none"
                    :class="focusRing"
                >
                    {{ authed ? 'Buka Dashboard' : 'Masuk' }}
                </Link>
            </div>
        </div>

        <nav
            v-show="menuOpen"
            id="menu-seluler"
            aria-label="Navigasi halaman (seluler)"
            class="border-t border-landing-line bg-landing-bg px-4 pb-4 md:hidden"
        >
            <ul class="flex flex-col pt-2">
                <li v-for="link in links" :key="link.id">
                    <a
                        :href="`#${link.id}`"
                        class="flex min-h-12 items-center rounded-xl px-3 text-base font-medium text-landing-fg transition-colors duration-150 hover:bg-landing-surface active:bg-landing-line"
                        :class="focusRing"
                        @click="go($event, link.id)"
                    >
                        {{ link.label }}
                    </a>
                </li>
            </ul>
        </nav>
    </header>
</template>

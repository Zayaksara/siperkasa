<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    links: { url: string | null; label: string; active: boolean }[];
}>();

const clean = (label: string) =>
    label
        .replace('&laquo; Previous', 'Sebelumnya')
        .replace('Next &raquo;', 'Berikutnya')
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›');
</script>

<template>
    <nav
        v-if="links.length > 3"
        aria-label="Halaman"
        class="flex flex-wrap items-center justify-center gap-1"
    >
        <template v-for="(link, i) in links" :key="i">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                :aria-current="link.active ? 'page' : undefined"
                class="inline-flex h-11 min-w-11 items-center justify-center rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="
                    link.active
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border hover:bg-accent'
                "
            >
                {{ clean(link.label) }}
            </Link>
            <span
                v-else
                class="inline-flex h-11 min-w-11 items-center justify-center rounded-md px-3 text-sm text-muted-foreground opacity-60"
            >
                {{ clean(link.label) }}
            </span>
        </template>
    </nav>
</template>

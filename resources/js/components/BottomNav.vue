<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { UserRound } from '@lucide/vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useRoleNav } from '@/composables/useRoleNav';
import { edit } from '@/routes/profile';
import type { NavItem } from '@/types';

const { items } = useRoleNav();
const { isCurrentOrParentUrl } = useCurrentUrl();

const tabs = computed<NavItem[]>(() => [
    ...items.value.slice(0, 4),
    { title: 'Profil', href: edit(), icon: UserRound },
]);
</script>

<template>
    <nav
        aria-label="Navigasi utama"
        class="fixed inset-x-0 bottom-0 z-40 flex border-t border-border bg-background pb-[env(safe-area-inset-bottom)] md:hidden"
    >
        <Link
            v-for="item in tabs"
            :key="item.title"
            :href="item.href"
            :aria-current="isCurrentOrParentUrl(item.href) ? 'page' : undefined"
            class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 px-1 text-xs font-medium text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-[current=page]:text-primary"
        >
            <component :is="item.icon" class="size-5" aria-hidden="true" />
            <span class="truncate">{{ item.title }}</span>
        </Link>
    </nav>
</template>

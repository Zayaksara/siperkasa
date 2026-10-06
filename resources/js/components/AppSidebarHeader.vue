<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

// Collapsed desktop sidebar gets a shorter header. Read from sidebar state,
// not a CSS :has() on the page wrapper: that selector made every SVG text
// measurement (ApexCharts getBBox) recalc style for the whole page.
const { state, isMobile } = useSidebar();
</script>

<template>
    <header
        class="flex shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear md:px-4"
        :class="!isMobile && state === 'collapsed' ? 'h-12' : 'h-16'"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="ml-auto"><NotificationBell /></div>
    </header>
</template>

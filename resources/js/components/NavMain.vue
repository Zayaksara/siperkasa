<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
}>();

const { currentItem } = useCurrentUrl();

const activeItem = computed(() => currentItem(props.items));

// Items without a group share the default "Menu" label; first-seen order is kept.
const groups = computed(() => {
    const byLabel = new Map<string, NavItem[]>();

    for (const item of props.items) {
        const label = item.group ?? 'Menu';
        byLabel.set(label, [...(byLabel.get(label) ?? []), item]);
    }

    return [...byLabel].map(([label, items]) => ({ label, items }));
});
</script>

<template>
    <SidebarGroup
        v-for="(group, index) in groups"
        :key="group.label"
        class="px-2 py-0"
    >
        <SidebarGroupLabel :id="`nav-group-${index}`">{{
            group.label
        }}</SidebarGroupLabel>
        <SidebarMenu :aria-labelledby="`nav-group-${index}`">
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="item === activeItem"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>

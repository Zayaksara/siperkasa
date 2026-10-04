<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { index, read, readAll } from '@/routes/notifications';
import type { NotificationItem } from '@/types/notification';

defineProps<{
    notifications: {
        data: NotificationItem[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Notifikasi', href: index() }] },
});
</script>

<template>
    <Head title="Notifikasi" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
        <PageHeader
            title="Notifikasi"
            description="Pembaruan tentang laporan Anda."
        >
            <Button
                variant="outline"
                class="h-11"
                :disabled="notifications.data.every((n) => n.read)"
                @click="router.post(readAll.url())"
            >
                Tandai semua dibaca
            </Button>
        </PageHeader>

        <EmptyState
            v-if="notifications.data.length === 0"
            title="Belum ada notifikasi"
            description="Pembaruan laporan akan muncul di sini."
        />

        <ul v-else class="divide-y rounded-xl border">
            <li v-for="item in notifications.data" :key="item.id">
                <button
                    type="button"
                    class="flex min-h-14 w-full items-start gap-3 p-3 text-left hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="router.post(read.url(item.id))"
                >
                    <span
                        class="mt-1.5 size-2.5 shrink-0 rounded-full"
                        :class="item.read ? '' : 'bg-primary'"
                        aria-hidden="true"
                    />
                    <span class="min-w-0">
                        <span
                            class="block text-sm"
                            :class="
                                item.read
                                    ? 'text-muted-foreground'
                                    : 'font-semibold'
                            "
                        >
                            <span v-if="!item.read" class="sr-only"
                                >Belum dibaca: </span
                            >{{ item.message }}
                        </span>
                        <span class="block text-xs text-muted-foreground">{{
                            formatDateTime(item.created_at)
                        }}</span>
                    </span>
                </button>
            </li>
        </ul>

        <Pagination :links="notifications.links" />
    </div>
</template>

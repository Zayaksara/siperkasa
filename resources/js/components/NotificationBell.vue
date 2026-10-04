<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime } from '@/lib/format';
import {
    index,
    read,
    readAll,
    recent,
    unreadCount,
} from '@/routes/notifications';
import type { NotificationItem } from '@/types/notification';

const POLL_MS = 60_000;

const page = usePage();
const count = ref(page.props.unreadNotificationsCount ?? 0);
const label = computed(() => (count.value > 99 ? '99+' : String(count.value)));

// Every Inertia visit carries a fresh shared count, so resync on it.
watch(
    () => page.props.unreadNotificationsCount,
    (value) => {
        count.value = value ?? 0;
    },
);

const items = ref<NotificationItem[]>([]);
const loading = ref(false);

async function getJson<T>(url: string): Promise<T | null> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    return response.ok ? ((await response.json()) as T) : null;
}

async function loadRecent(open: boolean) {
    if (!open) return;

    loading.value = true;
    try {
        const body = await getJson<{ data: NotificationItem[] }>(recent.url());
        if (body) items.value = body.data;
    } catch {
        // keep the previous list when offline
    } finally {
        loading.value = false;
    }
}

async function refreshCount() {
    if (document.hidden) return;

    try {
        const body = await getJson<{ count: number }>(unreadCount.url());
        if (body) count.value = body.count;
    } catch {
        // polling is best effort
    }
}

function onVisibility() {
    if (!document.hidden) void refreshCount();
}

let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    timer = setInterval(refreshCount, POLL_MS);
    document.addEventListener('visibilitychange', onVisibility);
});

onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisibility);
});
</script>

<template>
    <DropdownMenu @update:open="loadRecent">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative size-11"
                :aria-label="
                    count > 0
                        ? `Notifikasi, ${count} belum dibaca`
                        : 'Notifikasi'
                "
                data-test="notification-bell"
            >
                <Bell class="size-5" aria-hidden="true" />
                <span
                    v-if="count > 0"
                    class="absolute top-1 right-1 flex min-w-5 items-center justify-center rounded-full bg-destructive px-1 text-xs font-bold text-destructive-foreground"
                    data-test="notification-badge"
                    aria-hidden="true"
                >
                    {{ label }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            :collision-padding="12"
            class="w-[min(22rem,calc(100vw-1.5rem))]"
        >
            <DropdownMenuLabel>Notifikasi</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <div
                v-if="loading && items.length === 0"
                class="flex flex-col gap-2 p-3"
                role="status"
                aria-label="Memuat notifikasi"
            >
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-3 w-1/3" />
                <Skeleton class="h-4 w-5/6" />
            </div>
            <p
                v-else-if="items.length === 0"
                class="p-3 text-sm text-muted-foreground"
            >
                Belum ada notifikasi.
            </p>
            <DropdownMenuItem
                v-for="item in items"
                :key="item.id"
                class="min-h-11 flex-col items-start gap-0.5"
                @select="router.post(read.url(item.id))"
            >
                <StatusBadge v-if="item.status" :status="item.status" />

                <span
                    class="text-sm"
                    :class="
                        item.read ? 'text-muted-foreground' : 'font-semibold'
                    "
                >
                    <span v-if="!item.read" class="sr-only">Belum dibaca: </span
                    >{{ item.message }}
                </span>
                <span class="text-xs text-muted-foreground">{{
                    formatDateTime(item.created_at)
                }}</span>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                v-if="count > 0"
                class="min-h-11 justify-center font-medium"
                @select="router.post(readAll.url())"
            >
                Tandai semua dibaca
            </DropdownMenuItem>
            <DropdownMenuItem
                as-child
                class="min-h-11 justify-center font-medium"
            >
                <Link :href="index.url()">Lihat semua</Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

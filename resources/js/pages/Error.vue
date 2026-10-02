<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

const props = defineProps<{ status: number }>();

const copy = computed(
    () =>
        ({
            403: ['Akses ditolak', 'Anda tidak berhak membuka halaman ini.'],
            404: [
                'Halaman tidak ditemukan',
                'Alamat yang Anda tuju tidak ada.',
            ],
            500: [
                'Terjadi kesalahan',
                'Ada masalah di server. Coba lagi nanti.',
            ],
            503: [
                'Sedang pemeliharaan',
                'Aplikasi akan kembali sebentar lagi.',
            ],
        })[props.status] ?? ['Terjadi kesalahan', 'Coba lagi nanti.'],
);
</script>

<template>
    <Head :title="copy[0]" />
    <main
        class="flex min-h-svh flex-col items-center justify-center gap-4 bg-background p-6 text-center"
    >
        <BrandLogo :size="64" />
        <p class="text-5xl font-bold text-primary">{{ status }}</p>
        <h1 class="text-xl font-semibold">{{ copy[0] }}</h1>
        <p class="max-w-sm text-sm text-muted-foreground">{{ copy[1] }}</p>
        <Button as-child class="h-11">
            <Link :href="home()">Kembali ke beranda</Link>
        </Button>
    </main>
</template>

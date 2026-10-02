<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes/pic';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Beranda', href: dashboard() }] },
});
</script>

<template>
    <Head title="Beranda" />
    <div class="p-4">
        <h1 class="text-xl font-semibold">Beranda</h1>
        <p class="text-sm text-muted-foreground">Ringkasan tugas Anda.</p>
    </div>
</template>
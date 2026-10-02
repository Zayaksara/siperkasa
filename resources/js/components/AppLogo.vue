<script setup lang="ts">
import BrandLogo from '@/components/BrandLogo.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-white p-0.5"
    >
        <BrandLogo :size="22" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold"
            >SiPerkasa</span
        >
    </div>
</template>

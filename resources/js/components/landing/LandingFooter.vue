<script setup lang="ts">
const year = new Date().getFullYear();
</script>

<template>
    <footer class="mt-16 border-t border-landing-line">
        <div class="mx-auto max-w-6xl px-4 py-10 text-center sm:px-6">
            <p class="text-[15px] font-semibold text-landing-fg">SiPerkasa</p>
            <p class="text-[13px] text-landing-muted">
                &copy; {{ year }} UIN Siber Syekh Nurjati Cirebon
            </p>
        </div>
    </footer>
</template>

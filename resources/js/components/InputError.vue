<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p v-if="message" role="alert" class="text-sm text-destructive">
            {{ message }}
        </p>
    </div>
</template>

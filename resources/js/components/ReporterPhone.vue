<script setup lang="ts">
import { MessageCircle, Phone } from '@lucide/vue';
import { telUrl, whatsAppUrl } from '@/lib/phone';

defineProps<{ phone: string }>();

const linkClass =
    'inline-flex min-h-11 items-center gap-2 rounded-lg border px-3 text-sm font-medium hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <span class="font-medium break-all">{{ phone }}</span>
        <a
            :href="telUrl(phone)"
            :class="linkClass"
            aria-label="Telepon pelapor"
        >
            <Phone class="size-4" aria-hidden="true" />
            Telepon
        </a>
        <a
            :href="whatsAppUrl(phone)"
            target="_blank"
            rel="noopener noreferrer"
            :class="linkClass"
            aria-label="WhatsApp pelapor"
        >
            <MessageCircle class="size-4" aria-hidden="true" />
            WhatsApp
        </a>
    </div>
</template>

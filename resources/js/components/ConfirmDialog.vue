<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        confirmLabel?: string;
        destructive?: boolean;
        reasonLabel?: string;
        reasonMin?: number;
        processing?: boolean;
        error?: string;
    }>(),
    { confirmLabel: 'Ya, lanjutkan', reasonMin: 10 },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [reason?: string];
}>();

const reason = ref('');
watch(
    () => props.open,
    (open) => {
        if (open) reason.value = '';
    },
);

const needsReason = computed(() => !!props.reasonLabel);
const reasonValid = computed(
    () => !needsReason.value || reason.value.trim().length >= props.reasonMin,
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="needsReason" class="grid gap-2">
                <Label for="confirm-reason">{{ reasonLabel }}</Label>
                <Textarea id="confirm-reason" v-model="reason" :rows="4" />
                <p class="text-xs text-muted-foreground">
                    Minimal {{ reasonMin }} karakter.
                </p>
            </div>

            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

            <DialogFooter class="gap-2">
                <Button
                    variant="outline"
                    class="h-11"
                    :disabled="processing"
                    @click="emit('update:open', false)"
                >
                    Batal
                </Button>
                <Button
                    class="h-11"
                    :variant="destructive ? 'destructive' : 'default'"
                    :disabled="processing || !reasonValid"
                    @click="
                        emit('confirm', needsReason ? reason.trim() : undefined)
                    "
                >
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

```vue
<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 40 });
</script>

<template>
    <img
        src="/images/logo-uinssc.png"
        alt="Logo UIN Siber Syekh Nurjati Cirebon"
        :width="size"
        :height="Math.round(size * 1.286)"
        class="h-auto object-contain"
        :style="{ width: `${size}px` }"
    />
</template>
```

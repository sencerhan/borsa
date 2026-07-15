<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="currentColor"
        :class="className"
        v-bind="$attrs"
    >
        <!-- Sol mum (yükseliş) -->
        <rect x="5" y="2" width="2" height="4" rx="1" />
        <rect x="3" y="5" width="6" height="9" rx="1.5" />
        <rect x="5" y="13" width="2" height="4" rx="1" />
        <!-- Sağ mum -->
        <rect x="17" y="6" width="2" height="3" rx="1" />
        <rect x="15" y="8" width="6" height="10" rx="1.5" />
        <rect x="17" y="17" width="2" height="4" rx="1" />
    </svg>
</template>

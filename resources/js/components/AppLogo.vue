<script setup lang="ts">
import { CandlestickChart } from '@lucide/vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-linear-to-br from-emerald-500 to-teal-600 text-white"
    >
        <CandlestickChart class="size-5" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold"
            >BitKuruş Borsa</span
        >
    </div>
</template>

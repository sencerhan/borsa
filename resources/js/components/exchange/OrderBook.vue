<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { formatNumber } from '@/lib/number';
import type { Market, OrderBook } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    market: Market;
    orderBook: OrderBook;
}>();

const emit = defineEmits<{
    (e: 'select-price', price: string): void;
}>();

// Satışlar en iyi (düşük) fiyat altta olacak şekilde tersten gösterilir
const asks = computed(() => [...props.orderBook.asks].reverse());
const bids = computed(() => props.orderBook.bids);

const maxTotal = computed(() => {
    const totals = [...props.orderBook.asks, ...props.orderBook.bids].map((l) =>
        Number(l.total),
    );

    return totals.length ? Math.max(...totals) : 1;
});

function depthWidth(total: string): string {
    return `${Math.min(100, (Number(total) / maxTotal.value) * 100)}%`;
}
</script>

<template>
    <div class="flex h-full flex-col text-xs">
        <div
            class="grid grid-cols-3 gap-2 border-b px-3 py-2 text-muted-foreground"
        >
            <span>{{ t('common.price') }} ({{ market.quote_asset }})</span>
            <span class="text-right"
                >{{ t('common.quantity') }} ({{ market.base_asset }})</span
            >
            <span class="text-right">{{ t('common.total') }}</span>
        </div>

        <!-- Satış emirleri (asks) -->
        <div
            class="flex flex-1 flex-col justify-end gap-px overflow-hidden py-1"
        >
            <button
                v-for="(level, i) in asks"
                :key="`ask-${i}`"
                type="button"
                class="relative grid grid-cols-3 gap-2 px-3 py-0.5 text-right hover:bg-muted/50"
                @click="emit('select-price', level.price)"
            >
                <span
                    class="absolute inset-y-0 right-0 bg-red-500/10"
                    :style="{ width: depthWidth(level.total) }"
                />
                <span class="relative text-left font-medium text-red-500">{{
                    formatNumber(level.price, market.price_scale)
                }}</span>
                <span class="relative">{{
                    formatNumber(level.quantity, market.size_scale)
                }}</span>
                <span class="relative text-muted-foreground">{{
                    formatNumber(level.total, 2)
                }}</span>
            </button>
            <p
                v-if="!asks.length"
                class="px-3 py-2 text-center text-muted-foreground"
            >
                {{ t('exchange.no_sell') }}
            </p>
        </div>

        <!-- Son fiyat -->
        <div class="border-y px-3 py-1.5 text-sm font-semibold">
            <span
                :class="
                    market.last_price
                        ? 'text-foreground'
                        : 'text-muted-foreground'
                "
            >
                {{
                    market.last_price
                        ? formatNumber(market.last_price, market.price_scale)
                        : '—'
                }}
                <span class="text-xs font-normal text-muted-foreground">{{
                    market.quote_asset
                }}</span>
            </span>
        </div>

        <!-- Alış emirleri (bids) -->
        <div class="flex flex-1 flex-col gap-px overflow-hidden py-1">
            <button
                v-for="(level, i) in bids"
                :key="`bid-${i}`"
                type="button"
                class="relative grid grid-cols-3 gap-2 px-3 py-0.5 text-right hover:bg-muted/50"
                @click="emit('select-price', level.price)"
            >
                <span
                    class="absolute inset-y-0 right-0 bg-green-500/10"
                    :style="{ width: depthWidth(level.total) }"
                />
                <span
                    class="relative text-left font-medium text-green-600 dark:text-green-500"
                    >{{ formatNumber(level.price, market.price_scale) }}</span
                >
                <span class="relative">{{
                    formatNumber(level.quantity, market.size_scale)
                }}</span>
                <span class="relative text-muted-foreground">{{
                    formatNumber(level.total, 2)
                }}</span>
            </button>
            <p
                v-if="!bids.length"
                class="px-3 py-2 text-center text-muted-foreground"
            >
                {{ t('exchange.no_buy') }}
            </p>
        </div>
    </div>
</template>

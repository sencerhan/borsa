<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { formatNumber } from '@/lib/number';
import type { Market, Trade } from '@/types';

const { t } = useI18n();

defineProps<{
    market: Market;
    trades: Trade[];
}>();

function time(value: string): string {
    return new Date(value).toLocaleTimeString('tr-TR');
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
            <span class="text-right">{{ t('common.time') }}</span>
        </div>
        <div class="flex-1 overflow-y-auto py-1">
            <div
                v-for="trade in trades"
                :key="trade.id"
                class="grid grid-cols-3 gap-2 px-3 py-0.5"
            >
                <span
                    class="font-medium"
                    :class="
                        trade.taker_side === 'buy'
                            ? 'text-green-600 dark:text-green-500'
                            : 'text-red-500'
                    "
                    >{{ formatNumber(trade.price, market.price_scale) }}</span
                >
                <span class="text-right">{{
                    formatNumber(trade.quantity, market.size_scale)
                }}</span>
                <span class="text-right text-muted-foreground">{{
                    time(trade.created_at)
                }}</span>
            </div>
            <p
                v-if="!trades.length"
                class="px-3 py-4 text-center text-muted-foreground"
            >
                {{ t('exchange.no_trades') }}
            </p>
        </div>
    </div>
</template>

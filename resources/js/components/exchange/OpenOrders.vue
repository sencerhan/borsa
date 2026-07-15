<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { formatNumber } from '@/lib/number';
import type { Market, Order } from '@/types';

const { t } = useI18n();

defineProps<{
    market: Market;
    orders: Order[];
}>();

const cancelling = ref<number | null>(null);

function cancel(order: Order): void {
    cancelling.value = order.id;
    router.delete(`/exchange/orders/${order.id}`, {
        preserveScroll: true,
        onFinish: () => (cancelling.value = null),
    });
}

function remaining(order: Order): string {
    return String(Number(order.quantity) - Number(order.filled_quantity));
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-160 text-left text-xs">
            <thead class="border-b text-muted-foreground">
                <tr>
                    <th class="px-3 py-2 font-normal">
                        {{ t('common.limit') }}/{{ t('common.market') }}
                    </th>
                    <th class="px-3 py-2 font-normal">
                        {{ t('common.buy') }}/{{ t('common.sell') }}
                    </th>
                    <th class="px-3 py-2 text-right font-normal">
                        {{ t('common.price') }}
                    </th>
                    <th class="px-3 py-2 text-right font-normal">
                        {{ t('common.quantity') }}
                    </th>
                    <th class="px-3 py-2 text-right font-normal">
                        {{ t('exchange.remaining') }}
                    </th>
                    <th class="px-3 py-2 text-right font-normal">
                        {{ t('common.status') }}
                    </th>
                    <th class="px-3 py-2 text-right font-normal">
                        {{ t('common.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="order in orders" :key="order.id" class="border-b">
                    <td class="px-3 py-2">
                        {{
                            order.type === 'limit'
                                ? t('common.limit')
                                : t('common.market')
                        }}
                    </td>
                    <td
                        class="px-3 py-2 font-medium"
                        :class="
                            order.side === 'buy'
                                ? 'text-green-600 dark:text-green-500'
                                : 'text-red-500'
                        "
                    >
                        {{
                            order.side === 'buy'
                                ? t('common.buy')
                                : t('common.sell')
                        }}
                    </td>
                    <td class="px-3 py-2 text-right">
                        {{
                            order.price
                                ? formatNumber(order.price, market.price_scale)
                                : t('common.market')
                        }}
                    </td>
                    <td class="px-3 py-2 text-right">
                        {{ formatNumber(order.quantity, market.size_scale) }}
                    </td>
                    <td class="px-3 py-2 text-right">
                        {{ formatNumber(remaining(order), market.size_scale) }}
                    </td>
                    <td class="px-3 py-2 text-right text-muted-foreground">
                        {{
                            order.status === 'partially_filled'
                                ? t('exchange.status_partial')
                                : t('exchange.status_open')
                        }}
                    </td>
                    <td class="px-3 py-2 text-right">
                        <Button
                            variant="ghost"
                            size="sm"
                            :disabled="cancelling === order.id"
                            @click="cancel(order)"
                            >{{ t('common.cancel') }}</Button
                        >
                    </td>
                </tr>
                <tr v-if="!orders.length">
                    <td
                        colspan="7"
                        class="px-3 py-6 text-center text-muted-foreground"
                    >
                        {{ t('exchange.no_open_orders') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

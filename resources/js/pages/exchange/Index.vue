<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { LockKeyhole } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import OpenOrders from '@/components/exchange/OpenOrders.vue';
import OrderBook from '@/components/exchange/OrderBook.vue';
import OrderForm from '@/components/exchange/OrderForm.vue';
import TradeHistory from '@/components/exchange/TradeHistory.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useI18n } from '@/composables/useI18n';
import { formatNumber } from '@/lib/number';
import type {
    Market,
    Order,
    OrderBook as OrderBookType,
    Ticker,
    Trade,
    Wallet,
} from '@/types';

const props = defineProps<{
    market: Market | null;
    orderBook: OrderBookType | null;
    recentTrades: Trade[];
    ticker: Ticker | null;
    openOrders: Order[];
    wallets: Wallet[];
}>();

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'İşlem', href: '/exchange' }],
    },
});

const selectedPrice = ref<string | null>(null);

function onSelectPrice(price: string): void {
    selectedPrice.value = price;
}

// Canlı veri için düzenli yenileme (polling) — yalnızca piyasa aktifse
let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (!props.market) {
        return;
    }

    timer = setInterval(() => {
        router.reload({
            only: [
                'orderBook',
                'recentTrades',
                'ticker',
                'openOrders',
                'wallets',
                'market',
            ],
        });
    }, 4000);
});

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
    }
});

const changePositive = () =>
    props.ticker?.change_percent == null ||
    Number(props.ticker.change_percent) >= 0;
</script>

<template>
    <Head :title="t('exchange.title')" />

    <!-- Piyasa kapalı durumu -->
    <div
        v-if="!market"
        class="flex flex-1 flex-col items-center justify-center gap-3 p-10 text-center"
    >
        <div
            class="flex size-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
        >
            <LockKeyhole class="size-7" />
        </div>
        <h2 class="text-lg font-semibold">{{ t('exchange.closed_title') }}</h2>
        <p class="max-w-sm text-sm text-muted-foreground">
            {{ t('exchange.closed_desc') }}
        </p>
    </div>

    <div v-else class="flex flex-col gap-4 p-4">
        <!-- Ticker -->
        <Card>
            <CardContent
                class="flex flex-wrap items-center gap-x-8 gap-y-2 py-4"
            >
                <div>
                    <p class="text-lg font-bold">{{ market.symbol }}</p>
                    <p class="text-xs text-muted-foreground">BitKuruş / Tether</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('exchange.last_price') }}
                    </p>
                    <p class="text-lg font-semibold">
                        {{
                            market.last_price
                                ? formatNumber(
                                      market.last_price,
                                      market.price_scale,
                                  )
                                : '—'
                        }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('exchange.change_24h') }}
                    </p>
                    <p
                        class="font-medium"
                        :class="
                            ticker?.change_percent == null
                                ? 'text-muted-foreground'
                                : changePositive()
                                  ? 'text-green-600 dark:text-green-500'
                                  : 'text-red-500'
                        "
                    >
                        {{
                            ticker?.change_percent != null
                                ? `${formatNumber(ticker.change_percent, 2)}%`
                                : '—'
                        }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('exchange.high_24h') }}
                    </p>
                    <p class="font-medium">
                        {{ formatNumber(ticker?.high, market.price_scale) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('exchange.low_24h') }}
                    </p>
                    <p class="font-medium">
                        {{ formatNumber(ticker?.low, market.price_scale) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('exchange.volume_24h') }} ({{ market.base_asset }})
                    </p>
                    <p class="font-medium">
                        {{ formatNumber(ticker?.volume, 2) }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-12">
            <!-- Emir defteri -->
            <Card class="lg:col-span-4">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">{{
                        t('exchange.order_book')
                    }}</CardTitle>
                </CardHeader>
                <CardContent class="h-105 px-0">
                    <OrderBook
                        v-if="orderBook"
                        :market="market"
                        :order-book="orderBook"
                        @select-price="onSelectPrice"
                    />
                </CardContent>
            </Card>

            <!-- Emir formu -->
            <Card class="lg:col-span-4">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">{{
                        t('exchange.place_order')
                    }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <OrderForm
                        :market="market"
                        :wallets="wallets"
                        :price-hint="selectedPrice"
                    />
                </CardContent>
            </Card>

            <!-- Son işlemler -->
            <Card class="lg:col-span-4">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">{{
                        t('exchange.recent_trades')
                    }}</CardTitle>
                </CardHeader>
                <CardContent class="h-105 px-0">
                    <TradeHistory :market="market" :trades="recentTrades" />
                </CardContent>
            </Card>
        </div>

        <!-- Açık emirler -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-sm">{{
                    t('exchange.my_open_orders')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="px-0">
                <OpenOrders :market="market" :orders="openOrders" />
            </CardContent>
        </Card>
    </div>
</template>

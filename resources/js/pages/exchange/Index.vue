<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { useEchoPublic, useEcho, useConnectionStatus } from '@laravel/echo-vue';
import { LockKeyhole } from '@lucide/vue';
import { onMounted, onUnmounted, ref, computed, watch } from 'vue';
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
        breadcrumbs: [{ title: 'nav.exchange', href: '/exchange' }],
    },
});

const selectedPrice = ref<string | null>(null);

function onSelectPrice(price: string): void {
    selectedPrice.value = price;
}

// Reverb Canlı Bağlantı Durumu
const connectionStatus = useConnectionStatus();
const page = usePage();
const userId = computed(() => (page.props.auth as any)?.user?.id);

// Props'ları local reaktif değişkenlerle sarmalayalım (Socket ile anlık güncellemek için)
const localOrderBook = ref(props.orderBook);
const localTrades = ref([...props.recentTrades]);
const localTicker = ref(props.ticker);
const localWallets = ref([...props.wallets]);
const localMarket = ref(props.market);
const localOpenOrders = ref([...props.openOrders]);

// Sayfa geçişleri veya Inertia reload durumlarında senkronizasyon
watch(
    () => props.orderBook,
    (val) => {
        localOrderBook.value = val;
    },
);
watch(
    () => props.recentTrades,
    (val) => {
        localTrades.value = [...val];
    },
);
watch(
    () => props.ticker,
    (val) => {
        localTicker.value = val;
    },
);
watch(
    () => props.wallets,
    (val) => {
        localWallets.value = [...val];
    },
);
watch(
    () => props.market,
    (val) => {
        localMarket.value = val;
    },
);
watch(
    () => props.openOrders,
    (val) => {
        localOpenOrders.value = [...val];
    },
);

// Reverb WebSockets Dinleyicileri
if (props.market) {
    // Kanal adı backend ile birebir aynı: sadece alfanümerik (BK$/USDT -> BKUSDT)
    const marketChannel = `market.${props.market.symbol.replace(/[^a-zA-Z0-9]/g, '')}`;

    // 1. Emir Defteri ve Ticker Güncellemeleri
    useEchoPublic(marketChannel, 'orderbook.updated', (payload: any) => {
        if (payload.symbol === props.market?.symbol) {
            localOrderBook.value = payload.orderBook;
            localTicker.value = payload.ticker;

            if (localMarket.value) {
                localMarket.value.last_price = payload.ticker.last;
            }
        }
    });

    // 2. Yeni Eşleşme (İşlem) ve Ticker Güncellemeleri
    useEchoPublic(marketChannel, 'trade.executed', (payload: any) => {
        if (payload.symbol === props.market?.symbol) {
            localTrades.value = [payload.trade, ...localTrades.value].slice(
                0,
                50,
            );
            localTicker.value = payload.ticker;

            if (localMarket.value) {
                localMarket.value.last_price = payload.trade.price;
            }

            // Açık emirleri sessizce reload et
            if (userId.value) {
                router.reload({ only: ['openOrders'] });
            }
        }
    });
}

// 3. Kullanıcı Cüzdan Değişim Dinleyicisi (Özel Kanal)
watch(
    userId,
    (id) => {
        if (id) {
            useEcho(
                `user.${id}`,
                'wallet.updated',
                (payload: any) => {
                    const idx = localWallets.value.findIndex(
                        (w) => w.asset === payload.asset,
                    );

                    if (idx !== -1) {
                        localWallets.value[idx].available = payload.available;
                        localWallets.value[idx].locked = payload.locked;
                    }

                    // Cüzdan değiştiğinde açık emirleri de senkronize et
                    router.reload({ only: ['openOrders'] });
                },
                [],
                'private',
            );
        }
    },
    { immediate: true },
);

// Canlı veri için yedek yavaş yenileme (polling)
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
    }, 15000); // 15 saniyede bir yedek senkronizasyon (Reverb aktif olduğu için yükü azalttık)
});

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
    }
});

const changePositive = () =>
    localTicker.value?.change_percent == null ||
    Number(localTicker.value.change_percent) >= 0;
</script>

<template>
    <Head :title="t('exchange.title')" />

    <!-- Piyasa kapalı durumu -->
    <div
        v-if="!localMarket"
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
                    <p class="text-lg font-bold">{{ localMarket.symbol }}</p>
                    <p class="text-xs text-muted-foreground">
                        BitKuruş / Tether
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('exchange.last_price') }}
                    </p>
                    <p class="font-mono text-lg font-semibold">
                        {{
                            localMarket.last_price
                                ? formatNumber(
                                      localMarket.last_price,
                                      localMarket.price_scale,
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
                        class="font-mono font-medium"
                        :class="
                            localTicker?.change_percent == null
                                ? 'text-muted-foreground'
                                : changePositive()
                                  ? 'text-green-600 dark:text-green-500'
                                  : 'text-red-500'
                        "
                    >
                        {{
                            localTicker?.change_percent != null
                                ? `${formatNumber(localTicker.change_percent, 2)}%`
                                : '—'
                        }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('exchange.high_24h') }}
                    </p>
                    <p class="font-mono font-medium text-foreground/90">
                        {{
                            formatNumber(
                                localTicker?.high,
                                localMarket.price_scale,
                            )
                        }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('exchange.low_24h') }}
                    </p>
                    <p class="font-mono font-medium text-foreground/90">
                        {{
                            formatNumber(
                                localTicker?.low,
                                localMarket.price_scale,
                            )
                        }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ t('exchange.volume_24h') }} ({{
                            localMarket.base_asset
                        }})
                    </p>
                    <p class="font-mono font-medium text-foreground/90">
                        {{ formatNumber(localTicker?.volume, 2) }}
                    </p>
                </div>

                <!-- Reverb Canlı Bağlantı Durumu Göstergesi -->
                <div class="ml-auto flex items-center">
                    <div
                        class="flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium transition-all duration-300"
                        :class="{
                            'border-green-500/20 bg-green-500/10 text-green-600 dark:text-green-400':
                                connectionStatus === 'connected',
                            'animate-pulse border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400':
                                connectionStatus === 'connecting',
                            'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400':
                                connectionStatus === 'disconnected',
                        }"
                    >
                        <span class="relative flex h-1.5 w-1.5">
                            <span
                                v-if="connectionStatus === 'connected'"
                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"
                            ></span>
                            <span
                                class="relative inline-flex h-1.5 w-1.5 rounded-full"
                                :class="{
                                    'bg-green-500':
                                        connectionStatus === 'connected',
                                    'bg-amber-500':
                                        connectionStatus === 'connecting',
                                    'bg-red-500':
                                        connectionStatus === 'disconnected',
                                }"
                            ></span>
                        </span>
                        Reverb:
                        {{
                            connectionStatus === 'connected'
                                ? 'Canlı'
                                : connectionStatus === 'connecting'
                                  ? 'Bağlanıyor'
                                  : 'Bağlantı Yok'
                        }}
                    </div>
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
                        v-if="localOrderBook"
                        :market="localMarket"
                        :order-book="localOrderBook"
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
                        :market="localMarket"
                        :wallets="localWallets"
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
                    <TradeHistory :market="localMarket" :trades="localTrades" />
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
                <OpenOrders :market="localMarket" :orders="localOpenOrders" />
            </CardContent>
        </Card>
    </div>
</template>

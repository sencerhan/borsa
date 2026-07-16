<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CandlestickChart,
    ListOrdered,
    TrendingUp,
    Wallet as WalletIcon,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useI18n } from '@/composables/useI18n';
import { formatNumber } from '@/lib/number';
import { dashboard } from '@/routes';
import type { Market, Order, Ticker, Wallet } from '@/types';

const props = defineProps<{
    market: Market | null;
    ticker: Ticker | null;
    wallets: Wallet[];
    portfolioValue: string | null;
    openOrdersCount: number;
    recentOrders: Order[];
}>();

const { t } = useI18n();
const page = usePage();
const userName = computed(() => page.props.auth.user?.name ?? '');

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'nav.dashboard', href: dashboard() }],
    },
});

const priceScale = computed(() => props.market?.price_scale ?? 6);
const changePositive = computed(
    () =>
        props.ticker?.change_percent === null ||
        props.ticker?.change_percent === undefined ||
        Number(props.ticker.change_percent) >= 0,
);

const hasBalance = computed(() =>
    props.wallets.some((w) => Number(w.available) > 0 || Number(w.locked) > 0),
);

function statusLabel(status: string): string {
    return t(`order_status.${status}`);
}

function sideLabel(side: string): string {
    return side === 'buy' ? t('common.buy') : t('common.sell');
}
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <div class="flex flex-col gap-4 p-4">
        <!-- Karşılama -->
        <div>
            <h1 class="text-2xl font-bold tracking-tight">
                {{ t('dashboard.greeting', { name: userName }) }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('dashboard.subtitle') }}
            </p>
        </div>

        <!-- Özet istatistikler -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <TrendingUp class="size-4" />
                        <span class="text-xs">{{
                            t('dashboard.portfolio_value')
                        }}</span>
                    </div>
                    <p class="mt-2 text-2xl font-bold">
                        {{ formatNumber(portfolioValue, 2) }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >USDT</span
                        >
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <ListOrdered class="size-4" />
                        <span class="text-xs">{{
                            t('dashboard.open_orders')
                        }}</span>
                    </div>
                    <p class="mt-2 text-2xl font-bold">{{ openOrdersCount }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <CandlestickChart class="size-4" />
                        <span class="text-xs">{{
                            t('dashboard.last_price')
                        }}</span>
                    </div>
                    <p class="mt-2 text-2xl font-bold">
                        {{
                            market?.last_price
                                ? formatNumber(market.last_price, priceScale)
                                : '—'
                        }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <TrendingUp class="size-4" />
                        <span class="text-xs">{{
                            t('dashboard.change_24h')
                        }}</span>
                    </div>
                    <p
                        class="mt-2 text-2xl font-bold"
                        :class="
                            ticker?.change_percent == null
                                ? ''
                                : changePositive
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
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Bakiyeler -->
            <Card class="lg:col-span-2">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">{{
                        t('dashboard.balances')
                    }}</CardTitle>
                </CardHeader>
                <CardContent class="px-0">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b text-xs text-muted-foreground">
                            <tr>
                                <th class="px-6 py-2 font-normal">
                                    {{ t('common.asset') }}
                                </th>
                                <th class="px-6 py-2 text-right font-normal">
                                    {{ t('common.available') }}
                                </th>
                                <th class="px-6 py-2 text-right font-normal">
                                    {{ t('common.locked') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="w in wallets"
                                :key="w.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-6 py-3 font-medium">
                                    {{ w.asset }}
                                </td>
                                <td class="px-6 py-3 text-right">
                                    {{ formatNumber(w.available, 8) }}
                                </td>
                                <td
                                    class="px-6 py-3 text-right text-muted-foreground"
                                >
                                    {{ formatNumber(w.locked, 8) }}
                                </td>
                            </tr>
                            <tr v-if="!wallets.length">
                                <td
                                    colspan="3"
                                    class="px-6 py-6 text-center text-muted-foreground"
                                >
                                    {{ t('dashboard.empty_balance_hint') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="!hasBalance && wallets.length"
                        class="px-6 pt-3 text-xs text-muted-foreground"
                    >
                        {{ t('dashboard.empty_balance_hint') }}
                    </p>
                </CardContent>
            </Card>

            <!-- Hızlı aksiyonlar -->
            <div class="flex flex-col gap-4">
                <Link href="/exchange">
                    <Card class="transition-colors hover:border-primary">
                        <CardHeader>
                            <CandlestickChart class="size-6 text-primary" />
                            <CardTitle class="text-base">{{
                                t('dashboard.quick_trade')
                            }}</CardTitle>
                            <CardDescription>{{
                                t('dashboard.quick_trade_desc')
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <span
                                class="inline-flex items-center gap-1 text-sm font-medium text-primary"
                                >{{ t('dashboard.go_exchange') }}
                                <ArrowRight class="size-4"
                            /></span>
                        </CardContent>
                    </Card>
                </Link>

                <Link href="/wallet">
                    <Card class="transition-colors hover:border-primary">
                        <CardHeader>
                            <WalletIcon class="size-6 text-primary" />
                            <CardTitle class="text-base">{{
                                t('dashboard.deposit_cta')
                            }}</CardTitle>
                            <CardDescription>{{
                                t('dashboard.deposit_desc')
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <span
                                class="inline-flex items-center gap-1 text-sm font-medium text-primary"
                                >{{ t('dashboard.go_wallet') }}
                                <ArrowRight class="size-4"
                            /></span>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>

        <!-- Son emirler -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-base">{{
                    t('dashboard.recent_orders')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="px-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-140 text-left text-sm">
                        <thead class="border-b text-xs text-muted-foreground">
                            <tr>
                                <th class="px-6 py-2 font-normal">
                                    {{ t('common.buy') }}/{{ t('common.sell') }}
                                </th>
                                <th class="px-6 py-2 font-normal">
                                    {{ t('common.price') }}
                                </th>
                                <th class="px-6 py-2 text-right font-normal">
                                    {{ t('common.quantity') }}
                                </th>
                                <th class="px-6 py-2 text-right font-normal">
                                    {{ t('common.status') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="order in recentOrders"
                                :key="order.id"
                                class="border-b last:border-0"
                            >
                                <td
                                    class="px-6 py-3 font-medium"
                                    :class="
                                        order.side === 'buy'
                                            ? 'text-green-600 dark:text-green-500'
                                            : 'text-red-500'
                                    "
                                >
                                    {{ sideLabel(order.side) }}
                                </td>
                                <td class="px-6 py-3">
                                    {{
                                        order.price
                                            ? formatNumber(
                                                  order.price,
                                                  priceScale,
                                              )
                                            : t('common.market')
                                    }}
                                </td>
                                <td class="px-6 py-3 text-right">
                                    {{ formatNumber(order.quantity, 2) }}
                                </td>
                                <td
                                    class="px-6 py-3 text-right text-muted-foreground"
                                >
                                    {{ statusLabel(order.status) }}
                                </td>
                            </tr>
                            <tr v-if="!recentOrders.length">
                                <td
                                    colspan="4"
                                    class="px-6 py-6 text-center text-muted-foreground"
                                >
                                    {{ t('dashboard.no_orders') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

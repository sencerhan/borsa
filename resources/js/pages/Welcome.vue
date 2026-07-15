<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpenCheck,
    CandlestickChart,
    Percent,
    ShieldCheck,
    Wallet,
    Zap,
} from '@lucide/vue';
import { computed } from 'vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useI18n } from '@/composables/useI18n';
import { formatNumber } from '@/lib/number';
import { dashboard, login, register } from '@/routes';
import type { Market, OrderBook, Ticker } from '@/types';

const props = defineProps<{
    market: Market | null;
    ticker: Ticker | null;
    orderBook: OrderBook | null;
}>();

const { t } = useI18n();
const page = usePage();
const isAuthed = computed(() => Boolean(page.props.auth.user));

const changePositive = computed(
    () =>
        props.ticker?.change_percent === null ||
        props.ticker?.change_percent === undefined ||
        Number(props.ticker.change_percent) >= 0,
);

const features = [
    { icon: BookOpenCheck, key: 'orderbook' },
    { icon: Percent, key: 'fee' },
    { icon: Zap, key: 'fast' },
    { icon: Wallet, key: 'wallet' },
    { icon: ShieldCheck, key: 'secure' },
    { icon: CandlestickChart, key: 'live' },
];
</script>

<template>
    <Head title="BitKuruş Borsa — BK$ / USDT" />

    <div
        class="relative min-h-screen overflow-hidden bg-white text-neutral-900 dark:bg-[#080b12] dark:text-neutral-100"
    >
        <!-- Arka plan ışıltıları -->
        <div
            class="pointer-events-none absolute inset-0 overflow-hidden"
            aria-hidden="true"
        >
            <div
                class="absolute -top-40 -left-40 size-128 rounded-full bg-emerald-400/20 blur-[120px] dark:bg-emerald-500/15"
            />
            <div
                class="absolute -top-20 right-0 size-112 rounded-full bg-sky-400/20 blur-[120px] dark:bg-sky-500/10"
            />
            <div
                class="absolute bottom-0 left-1/3 size-120 rounded-full bg-indigo-400/10 blur-[120px] dark:bg-indigo-500/10"
            />
        </div>

        <div class="relative mx-auto max-w-6xl px-6">
            <!-- Navbar -->
            <header class="flex items-center justify-between py-6">
                <div class="flex items-center gap-2">
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/20"
                    >
                        <CandlestickChart class="size-5" />
                    </div>
                    <span class="text-lg font-bold tracking-tight"
                        >BitKuruş<span class="text-emerald-500">Borsa</span></span
                    >
                </div>

                <nav class="flex items-center gap-2">
                    <LanguageSwitcher />
                    <template v-if="isAuthed">
                        <Link
                            :href="dashboard()"
                            class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:opacity-90 dark:bg-white dark:text-neutral-900"
                            >{{ t('landing.go_to_panel') }}</Link
                        >
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-600 transition hover:text-neutral-900 dark:text-neutral-300 dark:hover:text-white"
                            >{{ t('landing.login') }}</Link
                        >
                        <Link
                            :href="register()"
                            class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:opacity-90 dark:bg-white dark:text-neutral-900"
                            >{{ t('landing.register') }}</Link
                        >
                    </template>
                </nav>
            </header>

            <!-- Hero -->
            <section
                class="grid items-center gap-10 py-12 lg:grid-cols-2 lg:py-20"
            >
                <div>
                    <div
                        class="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                    >
                        <span class="relative flex size-2">
                            <span
                                class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"
                            />
                            <span
                                class="relative inline-flex size-2 rounded-full bg-emerald-500"
                            />
                        </span>
                        {{ t('landing.badge_live') }} · BK$ / USDT
                    </div>

                    <h1
                        class="text-4xl leading-tight font-extrabold tracking-tight sm:text-5xl lg:text-6xl"
                    >
                        {{ t('landing.hero_pre') }}
                        <span
                            class="bg-linear-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent"
                            >{{ t('landing.hero_highlight') }}</span
                        >
                        {{ t('landing.hero_post') }}
                    </h1>

                    <p
                        class="mt-5 max-w-md text-lg text-neutral-600 dark:text-neutral-400"
                    >
                        {{ t('landing.hero_desc') }}
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            :href="isAuthed ? dashboard() : register()"
                            class="inline-flex items-center gap-2 rounded-xl bg-linear-to-br from-emerald-500 to-teal-600 px-6 py-3 font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:shadow-emerald-500/40"
                        >
                            {{
                                isAuthed
                                    ? t('landing.cta_start_trading')
                                    : t('landing.cta_start_free')
                            }}
                            <ArrowRight class="size-4" />
                        </Link>
                        <Link
                            :href="isAuthed ? '/exchange' : login()"
                            class="inline-flex items-center gap-2 rounded-xl border border-neutral-300 px-6 py-3 font-semibold transition hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800/60"
                        >
                            {{ t('landing.cta_view_market') }}
                        </Link>
                    </div>
                </div>

                <!-- Canlı piyasa kartı -->
                <div class="relative">
                    <div
                        class="rounded-2xl border border-neutral-200/80 bg-white/70 p-6 shadow-2xl shadow-neutral-900/5 backdrop-blur dark:border-white/10 dark:bg-white/5"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div
                                    class="flex size-8 items-center justify-center rounded-lg bg-linear-to-br from-emerald-500 to-teal-600 text-xs font-bold text-white"
                                >
                                    BK
                                </div>
                                <div>
                                    <p class="text-sm font-semibold">
                                        {{ market?.symbol ?? 'BK$ / USDT' }}
                                    </p>
                                    <p
                                        class="text-xs text-neutral-500 dark:text-neutral-400"
                                    >
                                        BitKuruş / Tether
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xl font-bold">
                                    {{
                                        market?.last_price
                                            ? formatNumber(
                                                  market.last_price,
                                                  market.price_scale,
                                              )
                                            : '—'
                                    }}
                                </p>
                                <p
                                    class="text-xs font-medium"
                                    :class="
                                        changePositive
                                            ? 'text-emerald-500'
                                            : 'text-red-500'
                                    "
                                >
                                    <template v-if="ticker?.change_percent"
                                        >{{ changePositive ? '▲' : '▼' }}
                                        {{
                                            formatNumber(
                                                ticker.change_percent,
                                                2,
                                            )
                                        }}%</template
                                    >
                                    <template v-else>24s</template>
                                </p>
                            </div>
                        </div>

                        <!-- Mini emir defteri -->
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <p
                                    class="mb-1 font-medium text-emerald-600 dark:text-emerald-400"
                                >
                                    Alış
                                </p>
                                <div class="space-y-1">
                                    <div
                                        v-for="(bid, i) in orderBook?.bids?.slice(
                                            0,
                                            5,
                                        ) ?? []"
                                        :key="`b-${i}`"
                                        class="relative flex justify-between overflow-hidden rounded px-2 py-1"
                                    >
                                        <span
                                            class="absolute inset-y-0 left-0 bg-emerald-500/10"
                                            :style="{
                                                width: `${Math.min(100, (Number(bid.quantity) / 6000) * 100)}%`,
                                            }"
                                        />
                                        <span
                                            class="relative text-emerald-600 dark:text-emerald-400"
                                            >{{
                                                formatNumber(
                                                    bid.price,
                                                    market?.price_scale ?? 6,
                                                )
                                            }}</span
                                        >
                                        <span
                                            class="relative text-neutral-500 dark:text-neutral-400"
                                            >{{
                                                formatNumber(bid.quantity, 0)
                                            }}</span
                                        >
                                    </div>
                                    <p
                                        v-if="!orderBook?.bids?.length"
                                        class="px-2 py-1 text-neutral-400"
                                    >
                                        —
                                    </p>
                                </div>
                            </div>
                            <div>
                                <p class="mb-1 font-medium text-red-500">
                                    Satış
                                </p>
                                <div class="space-y-1">
                                    <div
                                        v-for="(ask, i) in orderBook?.asks?.slice(
                                            0,
                                            5,
                                        ) ?? []"
                                        :key="`a-${i}`"
                                        class="relative flex justify-between overflow-hidden rounded px-2 py-1"
                                    >
                                        <span
                                            class="absolute inset-y-0 left-0 bg-red-500/10"
                                            :style="{
                                                width: `${Math.min(100, (Number(ask.quantity) / 6000) * 100)}%`,
                                            }"
                                        />
                                        <span class="relative text-red-500">{{
                                            formatNumber(
                                                ask.price,
                                                market?.price_scale ?? 6,
                                            )
                                        }}</span>
                                        <span
                                            class="relative text-neutral-500 dark:text-neutral-400"
                                            >{{
                                                formatNumber(ask.quantity, 0)
                                            }}</span
                                        >
                                    </div>
                                    <p
                                        v-if="!orderBook?.asks?.length"
                                        class="px-2 py-1 text-neutral-400"
                                    >
                                        —
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- 24s istatistik -->
                        <div
                            class="mt-4 grid grid-cols-3 gap-2 border-t border-neutral-200 pt-4 text-center text-xs dark:border-white/10"
                        >
                            <div>
                                <p class="text-neutral-500 dark:text-neutral-400">
                                    {{ t('landing.high_24h') }}
                                </p>
                                <p class="font-semibold">
                                    {{
                                        formatNumber(
                                            ticker?.high,
                                            market?.price_scale ?? 6,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-neutral-500 dark:text-neutral-400">
                                    {{ t('landing.low_24h') }}
                                </p>
                                <p class="font-semibold">
                                    {{
                                        formatNumber(
                                            ticker?.low,
                                            market?.price_scale ?? 6,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-neutral-500 dark:text-neutral-400">
                                    {{ t('landing.volume_24h') }}
                                </p>
                                <p class="font-semibold">
                                    {{ formatNumber(ticker?.volume, 0) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Özellikler -->
            <section class="py-12 lg:py-16">
                <h2 class="text-center text-2xl font-bold sm:text-3xl">
                    {{ t('landing.features_title') }}
                </h2>
                <p
                    class="mx-auto mt-2 max-w-lg text-center text-neutral-600 dark:text-neutral-400"
                >
                    {{ t('landing.features_desc') }}
                </p>

                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="feature in features"
                        :key="feature.key"
                        class="group rounded-2xl border border-neutral-200/80 bg-white/60 p-6 transition hover:border-emerald-500/40 hover:shadow-lg dark:border-white/10 dark:bg-white/5"
                    >
                        <div
                            class="mb-4 inline-flex size-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 transition group-hover:bg-emerald-500 group-hover:text-white dark:text-emerald-400"
                        >
                            <component :is="feature.icon" class="size-5" />
                        </div>
                        <h3 class="mb-1 font-semibold">
                            {{ t(`landing.f_${feature.key}_t`) }}
                        </h3>
                        <p
                            class="text-sm text-neutral-600 dark:text-neutral-400"
                        >
                            {{ t(`landing.f_${feature.key}_d`) }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="py-12">
                <div
                    class="relative overflow-hidden rounded-3xl bg-linear-to-br from-emerald-500 to-teal-600 px-8 py-12 text-center text-white shadow-2xl shadow-emerald-500/20"
                >
                    <h2 class="text-2xl font-bold sm:text-3xl">
                        {{ t('landing.cta_title') }}
                    </h2>
                    <p class="mx-auto mt-2 max-w-md text-white/80">
                        {{ t('landing.cta_desc') }}
                    </p>
                    <Link
                        :href="isAuthed ? dashboard() : register()"
                        class="mt-6 inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 font-semibold text-emerald-700 transition hover:bg-white/90"
                    >
                        {{
                            isAuthed
                                ? t('landing.go_to_panel')
                                : t('landing.cta_register')
                        }}
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
            </section>

            <!-- Footer -->
            <footer
                class="flex flex-col items-center justify-between gap-4 border-t border-neutral-200 py-8 text-sm text-neutral-500 sm:flex-row dark:border-white/10 dark:text-neutral-400"
            >
                <div class="flex items-center gap-2">
                    <CandlestickChart class="size-4 text-emerald-500" />
                    <span>BitKuruş Borsa</span>
                </div>
                <p>
                    © {{ new Date().getFullYear() }} BitKuruş Borsa.
                    {{ t('landing.rights') }}
                </p>
            </footer>
        </div>
    </div>
</template>

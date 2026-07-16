<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/useI18n';
import { formatNumber, formatPercent } from '@/lib/number';
import type { Market, OrderSide, OrderType, Wallet } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    market: Market;
    wallets: Wallet[];
    priceHint: string | null;
}>();

const form = useForm({
    market_id: props.market.id,
    side: 'buy' as OrderSide,
    type: 'limit' as OrderType,
    price: '',
    quantity: '',
    quote_amount: '',
});

// Emir defterinden fiyat seçilince forma yansıt
watch(
    () => props.priceHint,
    (price) => {
        if (price && form.type === 'limit') {
            form.price = price;
        }
    },
);

const page = usePage();
// Domain (iş kuralı) hataları back()->withErrors(['exchange' => ...]) ile gelir
const serverError = computed(
    () => (page.props.errors as Record<string, string>)?.exchange,
);

const isLimit = computed(() => form.type === 'limit');
const isMarketBuy = computed(
    () => form.type === 'market' && form.side === 'buy',
);

function balance(asset: string): string {
    return props.wallets.find((w) => w.asset === asset)?.available ?? '0';
}

const availableQuote = computed(() => balance(props.market.quote_asset));
const availableBase = computed(() => balance(props.market.base_asset));

// Limit emirde tahmini toplam (fiyat × miktar)
const estimatedTotal = computed(() => {
    if (!isLimit.value) {
        return null;
    }

    const total = Number(form.price) * Number(form.quantity);

    return Number.isFinite(total) && total > 0 ? total : null;
});

function setSide(side: OrderSide): void {
    form.side = side;
    form.clearErrors();
}

function setType(type: OrderType): void {
    form.type = type;
    form.clearErrors();
}

function submit(): void {
    form.transform((data) => ({
        market_id: data.market_id,
        side: data.side,
        type: data.type,
        price: isLimit.value ? data.price : null,
        quantity: isMarketBuy.value ? null : data.quantity,
        quote_amount: isMarketBuy.value ? data.quote_amount : null,
    })).post('/exchange/orders', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('price', 'quantity', 'quote_amount');
        },
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Alış / Satış -->
        <div class="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1">
            <button
                type="button"
                class="rounded-md py-1.5 text-sm font-medium transition-colors"
                :class="
                    form.side === 'buy'
                        ? 'bg-green-600 text-white'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="setSide('buy')"
            >
                {{ t('common.buy') }}
            </button>
            <button
                type="button"
                class="rounded-md py-1.5 text-sm font-medium transition-colors"
                :class="
                    form.side === 'sell'
                        ? 'bg-red-500 text-white'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="setSide('sell')"
            >
                {{ t('common.sell') }}
            </button>
        </div>

        <!-- Limit / Market -->
        <div class="flex gap-4 text-sm">
            <button
                type="button"
                class="border-b-2 pb-1 font-medium transition-colors"
                :class="
                    form.type === 'limit'
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground'
                "
                @click="setType('limit')"
            >
                {{ t('common.limit') }}
            </button>
            <button
                type="button"
                class="border-b-2 pb-1 font-medium transition-colors"
                :class="
                    form.type === 'market'
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground'
                "
                @click="setType('market')"
            >
                {{ t('common.market') }}
            </button>
        </div>

        <p class="text-xs text-muted-foreground">
            {{ t('exchange.available') }}:
            <span class="font-medium text-foreground">
                {{
                    form.side === 'buy'
                        ? `${formatNumber(availableQuote, 2)} ${market.quote_asset}`
                        : `${formatNumber(availableBase, market.size_scale)} ${market.base_asset}`
                }}
            </span>
        </p>

        <form class="flex flex-col gap-3" @submit.prevent="submit">
            <!-- Fiyat (limit) -->
            <div v-if="isLimit" class="grid gap-1.5">
                <Label for="price"
                    >{{ t('common.price') }} ({{ market.quote_asset }})</Label
                >
                <Input
                    id="price"
                    v-model="form.price"
                    type="number"
                    step="any"
                    min="0"
                    placeholder="0.00"
                    required
                />
                <InputError :message="form.errors.price" />
            </div>

            <!-- Miktar (market alış hariç) -->
            <div v-if="!isMarketBuy" class="grid gap-1.5">
                <Label for="quantity"
                    >{{ t('common.quantity') }} ({{ market.base_asset }})</Label
                >
                <Input
                    id="quantity"
                    v-model="form.quantity"
                    type="number"
                    step="any"
                    min="0"
                    placeholder="0.00"
                    required
                />
                <InputError :message="form.errors.quantity" />
            </div>

            <!-- Tutar (market alış) -->
            <div v-else class="grid gap-1.5">
                <Label for="quote_amount"
                    >{{ t('common.amount') }} ({{ market.quote_asset }})</Label
                >
                <Input
                    id="quote_amount"
                    v-model="form.quote_amount"
                    type="number"
                    step="any"
                    min="0"
                    placeholder="0.00"
                    required
                />
                <InputError :message="form.errors.quote_amount" />
            </div>

            <p v-if="estimatedTotal" class="text-xs text-muted-foreground">
                {{ t('exchange.estimated_total') }}:
                <span class="font-medium text-foreground"
                    >{{ formatNumber(estimatedTotal, 2) }}
                    {{ market.quote_asset }}</span
                >
            </p>

            <InputError :message="serverError" />

            <Button
                type="submit"
                :disabled="form.processing"
                :class="
                    form.side === 'buy'
                        ? 'bg-green-600 hover:bg-green-600/90'
                        : 'bg-red-500 hover:bg-red-500/90'
                "
            >
                {{
                    form.side === 'buy'
                        ? t('exchange.buy_action')
                        : t('exchange.sell_action')
                }}
                {{ market.base_asset }}
            </Button>

            <p class="text-center text-[11px] text-muted-foreground">
                {{
                    t('exchange.fees', {
                        maker: formatPercent(market.maker_fee).replace('%', ''),
                        taker: formatPercent(market.taker_fee).replace('%', ''),
                    })
                }}
            </p>
        </form>
    </div>
</template>

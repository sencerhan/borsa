<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/useI18n';
import type { Market } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    markets: Market[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Market Ayarları', href: '/admin/markets' }],
    },
});

interface MarketForm {
    maker_fee: string;
    taker_fee: string;
    min_order_size: string;
    is_active: boolean;
}

// Her market için ayrı form
const forms = props.markets.map((market) => ({
    market,
    form: useForm<MarketForm>({
        maker_fee: market.maker_fee,
        taker_fee: market.taker_fee,
        min_order_size: market.min_order_size,
        is_active: market.is_active,
    }),
}));

function submit(
    marketId: number,
    form: ReturnType<typeof useForm<MarketForm>>,
): void {
    form.patch(`/admin/markets/${marketId}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('admin.title')" />

    <div class="flex flex-col gap-4 p-4">
        <Card v-for="{ market, form } in forms" :key="market.id">
            <CardHeader>
                <CardTitle>{{ market.symbol }}</CardTitle>
                <CardDescription>{{ t('admin.fees_desc') }}</CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    class="grid gap-4 sm:grid-cols-2"
                    @submit.prevent="submit(market.id, form)"
                >
                    <div class="grid gap-1.5">
                        <Label>{{ t('admin.maker_fee') }}</Label>
                        <Input
                            v-model="form.maker_fee"
                            type="number"
                            step="any"
                            min="0"
                            max="0.1"
                        />
                        <InputError :message="form.errors.maker_fee" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>{{ t('admin.taker_fee') }}</Label>
                        <Input
                            v-model="form.taker_fee"
                            type="number"
                            step="any"
                            min="0"
                            max="0.1"
                        />
                        <InputError :message="form.errors.taker_fee" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>{{ t('admin.min_order') }}</Label>
                        <Input
                            v-model="form.min_order_size"
                            type="number"
                            step="any"
                            min="0"
                        />
                        <InputError :message="form.errors.min_order_size" />
                    </div>
                    <div class="flex items-end gap-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="size-4 rounded border"
                            />
                            {{ t('common.active') }}
                        </label>
                    </div>
                    <div class="sm:col-span-2">
                        <Button type="submit" :disabled="form.processing"
                            >{{ t('common.save') }}</Button
                        >
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>

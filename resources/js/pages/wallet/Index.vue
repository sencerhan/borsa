<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { formatNumber } from '@/lib/number';
import type { Asset, Deposit, Wallet, Withdrawal } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    assets: Asset[];
    wallets: Wallet[];
    deposits: Deposit[];
    withdrawals: Withdrawal[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cüzdan', href: '/wallet' }],
    },
});

// Yatırma artık tutar girişi ile yapılmaz; kullanıcı sadece hangi varlığın
// adresini göreceğini seçer. Bakiye, adrese gelen gerçek zincir transferi
// doğrulandığında otomatik eklenir.
const depositAsset = ref(props.assets[0]?.symbol ?? '');

const withdrawForm = useForm({
    asset: props.assets[0]?.symbol ?? '',
    amount: '',
    address: '',
});

function scaleFor(symbol: string): number {
    return props.assets.find((a) => a.symbol === symbol)?.scale ?? 8;
}

function walletFor(symbol: string): Wallet | undefined {
    return props.wallets.find((w) => w.asset === symbol);
}

function networkFor(symbol: string): string | null {
    return props.assets.find((a) => a.symbol === symbol)?.network ?? null;
}

// Seçili varlığın yatırma adresi ve ağı
const depositAddress = computed(
    () => walletFor(depositAsset.value)?.deposit_address ?? null,
);
const depositNetwork = computed(() => networkFor(depositAsset.value));

const copied = ref(false);

async function copyAddress(): Promise<void> {
    if (!depositAddress.value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(depositAddress.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch {
        copied.value = false;
    }
}

function statusLabel(status: string): string {
    return t(`transfer_status.${status}`);
}

function submitWithdraw(): void {
    withdrawForm.post('/wallet/withdraw', {
        preserveScroll: true,
        onSuccess: () => withdrawForm.reset('amount', 'address'),
    });
}

const totalAssets = computed(() => props.assets);
</script>

<template>
    <Head :title="t('wallet.title')" />

    <div class="flex flex-col gap-4 p-4">
        <!-- Bakiyeler -->
        <Card>
            <CardHeader>
                <CardTitle>{{ t('wallet.my_balances') }}</CardTitle>
                <CardDescription>{{
                    t('wallet.balances_desc')
                }}</CardDescription>
            </CardHeader>
            <CardContent class="px-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-120 text-left text-sm">
                        <thead class="border-b text-muted-foreground">
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
                                v-for="asset in totalAssets"
                                :key="asset.id"
                                class="border-b"
                            >
                                <td class="px-6 py-3 font-medium">
                                    {{ asset.symbol }}
                                    <span class="text-muted-foreground"
                                        >· {{ asset.name }}</span
                                    >
                                </td>
                                <td class="px-6 py-3 text-right">
                                    {{
                                        formatNumber(
                                            walletFor(asset.symbol)
                                                ?.available ?? '0',
                                            asset.scale,
                                        )
                                    }}
                                </td>
                                <td
                                    class="px-6 py-3 text-right text-muted-foreground"
                                >
                                    {{
                                        formatNumber(
                                            walletFor(asset.symbol)?.locked ??
                                                '0',
                                            asset.scale,
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 md:grid-cols-2">
            <!-- Para yatır -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('wallet.deposit') }}</CardTitle>
                    <CardDescription>{{
                        t('wallet.deposit_desc')
                    }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col gap-3">
                        <div class="grid gap-1.5">
                            <Label for="dep-asset">{{
                                t('common.asset')
                            }}</Label>
                            <select
                                id="dep-asset"
                                v-model="depositAsset"
                                class="h-9 rounded-md border bg-transparent px-3 text-sm"
                            >
                                <option
                                    v-for="a in assets"
                                    :key="a.id"
                                    :value="a.symbol"
                                >
                                    {{ a.symbol }} — {{ a.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Yatırma adresi -->
                        <div class="grid gap-1.5">
                            <Label>{{ t('wallet.deposit_address') }}</Label>
                            <div
                                class="flex items-center gap-2 rounded-md border bg-muted/40 px-3 py-2"
                            >
                                <code
                                    class="flex-1 truncate text-xs"
                                    :title="depositAddress ?? ''"
                                    >{{ depositAddress ?? '—' }}</code
                                >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    :disabled="!depositAddress"
                                    :title="t('common.copy')"
                                    @click="copyAddress"
                                >
                                    <Check
                                        v-if="copied"
                                        class="size-4 text-green-600"
                                    />
                                    <Copy v-else class="size-4" />
                                </Button>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    t('wallet.network_warning', {
                                        asset: depositAsset,
                                        network: depositNetwork
                                            ? t('wallet.network_paren', {
                                                  network: depositNetwork,
                                              })
                                            : '',
                                    })
                                }}
                            </p>
                        </div>

                        <!-- Gerçek zincir transferi bilgisi -->
                        <p
                            class="rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400"
                        >
                            {{ t('wallet.deposit_onchain_note') }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Para çek -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('wallet.withdraw') }}</CardTitle>
                    <CardDescription>{{
                        t('wallet.withdraw_desc')
                    }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <form
                        class="flex flex-col gap-3"
                        @submit.prevent="submitWithdraw"
                    >
                        <div class="grid gap-1.5">
                            <Label for="wd-asset">{{
                                t('common.asset')
                            }}</Label>
                            <select
                                id="wd-asset"
                                v-model="withdrawForm.asset"
                                class="h-9 rounded-md border bg-transparent px-3 text-sm"
                            >
                                <option
                                    v-for="a in assets"
                                    :key="a.id"
                                    :value="a.symbol"
                                >
                                    {{ a.symbol }} — {{ a.name }}
                                </option>
                            </select>
                            <InputError :message="withdrawForm.errors.asset" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="wd-amount">{{
                                t('common.quantity')
                            }}</Label>
                            <Input
                                id="wd-amount"
                                v-model="withdrawForm.amount"
                                type="number"
                                step="any"
                                min="0"
                                placeholder="0.00"
                                required
                            />
                            <InputError :message="withdrawForm.errors.amount" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="wd-address">{{
                                t('wallet.address_optional')
                            }}</Label>
                            <Input
                                id="wd-address"
                                v-model="withdrawForm.address"
                                type="text"
                                :placeholder="t('wallet.address_ph')"
                            />
                            <InputError
                                :message="withdrawForm.errors.address"
                            />
                        </div>
                        <!-- Domain hataları (ör. yetersiz bakiye) burada görünür -->
                        <InputError :message="withdrawForm.errors.exchange" />
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="withdrawForm.processing"
                            >{{ t('wallet.withdraw_btn') }}</Button
                        >
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Geçmiş -->
        <div class="grid gap-4 md:grid-cols-2">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">{{
                        t('wallet.deposit_history')
                    }}</CardTitle>
                </CardHeader>
                <CardContent class="px-0">
                    <table class="w-full text-left text-xs">
                        <tbody>
                            <tr
                                v-for="d in deposits"
                                :key="d.id"
                                class="border-b"
                            >
                                <td class="px-6 py-2 font-medium">
                                    {{ d.asset }}
                                </td>
                                <td class="px-6 py-2 text-right">
                                    {{
                                        formatNumber(
                                            d.amount,
                                            scaleFor(d.asset),
                                        )
                                    }}
                                </td>
                                <td
                                    class="px-6 py-2 text-right text-muted-foreground"
                                >
                                    {{ statusLabel(d.status) }}
                                </td>
                            </tr>
                            <tr v-if="!deposits.length">
                                <td
                                    class="px-6 py-4 text-center text-muted-foreground"
                                >
                                    {{ t('common.none') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">{{
                        t('wallet.withdraw_history')
                    }}</CardTitle>
                </CardHeader>
                <CardContent class="px-0">
                    <table class="w-full text-left text-xs">
                        <tbody>
                            <tr
                                v-for="w in withdrawals"
                                :key="w.id"
                                class="border-b"
                            >
                                <td class="px-6 py-2 font-medium">
                                    {{ w.asset }}
                                </td>
                                <td class="px-6 py-2 text-right">
                                    {{
                                        formatNumber(
                                            w.amount,
                                            scaleFor(w.asset),
                                        )
                                    }}
                                </td>
                                <td
                                    class="px-6 py-2 text-right text-muted-foreground"
                                >
                                    {{ statusLabel(w.status) }}
                                </td>
                            </tr>
                            <tr v-if="!withdrawals.length">
                                <td
                                    class="px-6 py-4 text-center text-muted-foreground"
                                >
                                    {{ t('common.none') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

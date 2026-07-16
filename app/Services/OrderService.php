<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\InvalidOrderException;
use App\Exceptions\MarketInactiveException;
use App\Exceptions\MarketNotFoundException;
use App\Exceptions\OrderNotFoundException;
use App\Models\Market;
use App\Models\Order;
use App\Models\User;
use App\Services\Data\PlaceOrderData;
use App\Support\BigMath;
use Illuminate\Support\Facades\DB;

/**
 * Emir yaşam döngüsü: doğrulama, fon kilitleme, emir oluşturma ve eşleştirme orchestrasyonu.
 */
class OrderService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly MatchingEngineService $engine,
    ) {}

    /**
     * Emir verir: fonu kilitler, emri oluşturur ve eşleştirir.
     * Piyasa bazında serileştirme için market satırı lockForUpdate ile kilitlenir.
     */
    public function place(User $user, PlaceOrderData $data): Order
    {
        $order = DB::transaction(function () use ($user, $data) {
            /** @var Market|null $market */
            $market = Market::whereKey($data->marketId)->lockForUpdate()->first();

            if ($market === null) {
                throw MarketNotFoundException::forSymbol((string) $data->marketId);
            }

            if (! $market->is_active) {
                throw MarketInactiveException::make();
            }

            [$lockAsset, $lockAmount, $quantity, $quoteAmount, $price] = $this->prepare($user, $market, $data);

            $order = Order::create([
                'user_id' => $user->id,
                'market_id' => $market->id,
                'side' => $data->side,
                'type' => $data->type,
                'status' => OrderStatus::Open,
                'price' => $price,
                'quantity' => $quantity,
                'filled_quantity' => '0',
                'quote_amount' => $quoteAmount,
                'quote_filled' => '0',
                'locked_asset' => $lockAsset,
                'locked_amount' => $lockAmount,
            ]);

            // Fonu kilitle (yetersizse InsufficientBalanceException → rollback)
            $this->wallets->lock($user->id, $lockAsset, $lockAmount, $order);

            $this->engine->match($order, $market);

            return $order->refresh();
        });

        event(new \App\Events\OrderBookUpdated($order->market));

        return $order;
    }

    /**
     * Açık/kısmi emri iptal eder ve kalan kilitli fonu iade eder.
     */
    public function cancel(User $user, int $orderId): Order
    {
        $order = DB::transaction(function () use ($user, $orderId) {
            /** @var Order|null $order */
            $order = Order::whereKey($orderId)->lockForUpdate()->first();

            if ($order === null || $order->user_id !== $user->id) {
                throw OrderNotFoundException::make();
            }

            if (! $order->status->isActive()) {
                throw InvalidOrderException::make(__('exchange.errors.order_not_cancelable'));
            }

            if ($order->locked_asset !== null && BigMath::isPositive($order->locked_amount)) {
                $this->wallets->unlock($user->id, $order->locked_asset, $order->locked_amount, LedgerEntryType::Unlock, $order);
            }

            $order->locked_amount = '0';
            $order->status = OrderStatus::Cancelled;
            $order->save();

            return $order;
        });

        event(new \App\Events\OrderBookUpdated($order->market));

        return $order;
    }

    /**
     * Emir tipine göre doğrulama yapar ve kilitlenecek varlık/tutarı hesaplar.
     *
     * @return array{0:string,1:string,2:string,3:string|null,4:string|null}
     *                                                                       [lockAsset, lockAmount, quantity, quoteAmount, price]
     */
    private function prepare(User $user, Market $market, PlaceOrderData $data): array
    {
        if ($data->type === OrderType::Limit) {
            return $this->prepareLimit($market, $data);
        }

        return $this->prepareMarket($user, $market, $data);
    }

    /**
     * @return array{0:string,1:string,2:string,3:string|null,4:string|null}
     */
    private function prepareLimit(Market $market, PlaceOrderData $data): array
    {
        // Fiyat ve miktarı market hassasiyetine (tick/lot) indirger
        $price = $this->scaleDown($data->price ?? '0', $market->price_scale);
        $quantity = $this->scaleDown($data->quantity ?? '0', $market->size_scale);

        if (! BigMath::isPositive($price) || ! BigMath::isPositive($quantity)) {
            throw InvalidOrderException::make(__('exchange.errors.invalid_price_quantity'));
        }

        $this->assertMinSize($market, $quantity);

        if ($data->side === OrderSide::Buy) {
            // Alış: quote kilitlenir (fiyat × miktar)
            return [$market->quote_asset, BigMath::mul($price, $quantity), $quantity, null, $price];
        }

        // Satış: base kilitlenir (miktar)
        return [$market->base_asset, $quantity, $quantity, null, $price];
    }

    /**
     * @return array{0:string,1:string,2:string,3:string|null,4:string|null}
     */
    private function prepareMarket(User $user, Market $market, PlaceOrderData $data): array
    {
        $this->assertBookNotEmpty($user, $market, $data->side);

        if ($data->side === OrderSide::Buy) {
            $quoteAmount = $data->quoteAmount ?? '0';
            if (! BigMath::isPositive($quoteAmount)) {
                throw InvalidOrderException::make(__('exchange.errors.invalid_quote_amount'));
            }

            // Market alış: quote bütçesi kilitlenir, base miktarı eşleşmede belirlenir
            return [$market->quote_asset, $quoteAmount, '0', $quoteAmount, null];
        }

        $quantity = $this->scaleDown($data->quantity ?? '0', $market->size_scale);
        if (! BigMath::isPositive($quantity)) {
            throw InvalidOrderException::make(__('exchange.errors.invalid_price_quantity'));
        }

        $this->assertMinSize($market, $quantity);

        // Market satış: base miktarı kilitlenir
        return [$market->base_asset, $quantity, $quantity, null, null];
    }

    private function assertMinSize(Market $market, string $quantity): void
    {
        if (BigMath::isPositive($market->min_order_size) && BigMath::lt($quantity, $market->min_order_size)) {
            throw InvalidOrderException::belowMinimum($market->min_order_size);
        }
    }

    /** Değeri verilen ondalık haneye (market tick/lot) keserek indirger. */
    private function scaleDown(string $value, int $scale): string
    {
        return bcadd($value, '0', max(0, $scale));
    }

    /** Market emir için karşı tarafta (kendi emri hariç) eşleşecek emir bulunmalı. */
    private function assertBookNotEmpty(User $user, Market $market, OrderSide $side): void
    {
        $hasCounterparty = Order::query()
            ->active()
            ->where('market_id', $market->id)
            ->where('side', $side->opposite()->value)
            ->where('user_id', '!=', $user->id) // self-trade önleme
            ->exists();

        if (! $hasCounterparty) {
            throw InvalidOrderException::emptyBook();
        }
    }
}

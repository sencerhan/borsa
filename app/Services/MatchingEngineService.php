<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Market;
use App\Models\Order;
use App\Models\Trade;
use App\Support\BigMath;

/**
 * Emir eşleştirme motoru (fiyat-zaman önceliği).
 *
 * Gelen (taker) emri, karşı taraftaki dinlenen (maker) emirlerle eşleştirir.
 * İşlem fiyatı daima maker emrin fiyatıdır. Her metod, OrderService tarafından
 * açılan DB transaction ve market kilidi (lockForUpdate) içinde çalışır.
 */
class MatchingEngineService
{
    public function __construct(private readonly WalletService $wallets) {}

    /**
     * Taker emri deftere karşı eşleştirir ve kalan durumu sonlandırır.
     */
    public function match(Order $taker, Market $market): void
    {
        $base = $market->base_asset;
        $quote = $market->quote_asset;

        while ($this->takerHasRemaining($taker)) {
            $maker = $this->bestMaker($taker, $market);
            if ($maker === null) {
                break;
            }

            // Limit taker için fiyat uyumu kontrolü
            if ($taker->type === OrderType::Limit && ! $this->priceCrosses($taker, $maker)) {
                break;
            }

            $execPrice = $maker->price;
            $fillQty = $this->fillQuantity($taker, $maker, $execPrice);

            if (! BigMath::isPositive($fillQty)) {
                break;
            }

            $execQuote = BigMath::mul($execPrice, $fillQty);

            $this->settle($taker, $maker, $market, $base, $quote, $execPrice, $fillQty, $execQuote);

            $market->last_price = $execPrice;
        }

        $this->finalizeTaker($taker, $base, $quote);
        $market->save();
    }

    /** Taker'ın hâlâ karşılanacak miktarı/bütçesi var mı? */
    private function takerHasRemaining(Order $taker): bool
    {
        if ($this->isMarketBuy($taker)) {
            return BigMath::isPositive($this->quoteBudgetRemaining($taker));
        }

        return BigMath::isPositive($this->baseRemaining($taker));
    }

    /** Deftere karşı en iyi maker emri (fiyat-zaman önceliği), kilitlenerek. */
    private function bestMaker(Order $taker, Market $market): ?Order
    {
        $query = Order::query()
            ->active()
            ->where('market_id', $market->id)
            ->where('side', $taker->side->opposite()->value)
            ->where('id', '!=', $taker->id);

        if ($taker->side === OrderSide::Buy) {
            // Alıcı için en ucuz satış önce
            $query->orderBy('price')->orderBy('id');
        } else {
            // Satıcı için en pahalı alış önce
            $query->orderByDesc('price')->orderBy('id');
        }

        return $query->lockForUpdate()->first();
    }

    /** Limit taker fiyatı maker ile kesişiyor mu? */
    private function priceCrosses(Order $taker, Order $maker): bool
    {
        if ($taker->side === OrderSide::Buy) {
            return BigMath::gte($taker->price, $maker->price);
        }

        return BigMath::lte($taker->price, $maker->price);
    }

    /** Bu eşleşmede gerçekleşecek base miktarı. */
    private function fillQuantity(Order $taker, Order $maker, string $execPrice): string
    {
        $makerRemaining = $this->baseRemaining($maker);

        if ($this->isMarketBuy($taker)) {
            // Market alış: kalan bütçenin bu fiyattan alabileceği kadar
            $maxByBudget = BigMath::div($this->quoteBudgetRemaining($taker), $execPrice);

            return BigMath::min($makerRemaining, $maxByBudget);
        }

        return BigMath::min($this->baseRemaining($taker), $makerRemaining);
    }

    /**
     * Tek bir eşleşmeyi muhasebeleştirir: trade kaydı, bakiye transferleri, komisyon, emir güncelleme.
     */
    private function settle(
        Order $taker,
        Order $maker,
        Market $market,
        string $base,
        string $quote,
        string $execPrice,
        string $fillQty,
        string $execQuote,
    ): void {
        $buyOrder = $taker->side === OrderSide::Buy ? $taker : $maker;
        $sellOrder = $taker->side === OrderSide::Buy ? $maker : $taker;
        $buyerIsTaker = $taker->side === OrderSide::Buy;

        // Komisyon oranları: taker taker_fee, maker maker_fee öder
        $buyerRate = $buyerIsTaker ? $market->taker_fee : $market->maker_fee;
        $sellerRate = $buyerIsTaker ? $market->maker_fee : $market->taker_fee;

        $buyerFee = BigMath::percentOf($fillQty, $buyerRate);      // base cinsinden
        $sellerFee = BigMath::percentOf($execQuote, $sellerRate);  // quote cinsinden
        $buyerReceivesBase = BigMath::sub($fillQty, $buyerFee);
        $sellerReceivesQuote = BigMath::sub($execQuote, $sellerFee);

        $trade = Trade::create([
            'market_id' => $market->id,
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'buyer_id' => $buyOrder->user_id,
            'seller_id' => $sellOrder->user_id,
            'taker_side' => $taker->side,
            'price' => $execPrice,
            'quantity' => $fillQty,
            'quote_amount' => $execQuote,
            'buyer_fee' => $buyerFee,
            'seller_fee' => $sellerFee,
        ]);

        // Satıcının kilitli base'i borsadan çıkar → alıcıya (komisyon düşülmüş) base
        $this->wallets->debitLocked($sellOrder->user_id, $base, $fillQty, LedgerEntryType::Trade, $trade);
        $this->wallets->credit($buyOrder->user_id, $base, $buyerReceivesBase, LedgerEntryType::Trade, $trade);

        // Alıcının kilitli quote'u borsadan çıkar → satıcıya (komisyon düşülmüş) quote
        $this->wallets->debitLocked($buyOrder->user_id, $quote, $execQuote, LedgerEntryType::Trade, $trade);

        // Limit alış, işlem fiyatı emir fiyatından iyi (düşük) ise farkı iade et
        if ($buyOrder->type === OrderType::Limit && BigMath::gt($buyOrder->price, $execPrice)) {
            $refund = BigMath::mul(BigMath::sub($buyOrder->price, $execPrice), $fillQty);
            if (BigMath::isPositive($refund)) {
                $this->wallets->unlock($buyOrder->user_id, $quote, $refund, LedgerEntryType::Refund, $trade);
            }
        }

        $this->wallets->credit($sellOrder->user_id, $quote, $sellerReceivesQuote, LedgerEntryType::Trade, $trade);

        $this->applyFillToOrder($maker, $fillQty, $execQuote, $execPrice);
        $this->applyFillToOrder($taker, $fillQty, $execQuote, $execPrice);

        $this->updateStatus($maker);
    }

    /** Emrin gerçekleşen miktar/hacim ve kilit alanlarını günceller. */
    private function applyFillToOrder(Order $order, string $fillQty, string $execQuote, string $execPrice): void
    {
        $order->filled_quantity = BigMath::add($order->filled_quantity, $fillQty);
        $order->quote_filled = BigMath::add($order->quote_filled, $execQuote);
        $order->locked_amount = BigMath::max('0', BigMath::sub($order->locked_amount, $this->lockedReduction($order, $fillQty, $execQuote)));
        $order->save();
    }

    /** Bir emirde bu dolum için düşülecek kilitli tutar. */
    private function lockedReduction(Order $order, string $fillQty, string $execQuote): string
    {
        if ($order->side === OrderSide::Sell) {
            return $fillQty; // base kilitliydi
        }

        if ($order->type === OrderType::Limit) {
            return BigMath::mul($order->price, $fillQty); // emir fiyatından quote kilitliydi
        }

        return $execQuote; // market alış: harcanan quote
    }

    /** Emir tamamen/kısmen dolmuş olarak durumunu günceller. */
    private function updateStatus(Order $order): void
    {
        if (BigMath::gte($order->filled_quantity, $order->quantity) && BigMath::isPositive($order->quantity)) {
            $order->status = OrderStatus::Filled;
        } elseif (BigMath::isPositive($order->filled_quantity)) {
            $order->status = OrderStatus::PartiallyFilled;
        }

        $order->save();
    }

    /** Eşleşme bittikten sonra taker'ı sonlandırır (market artığı iptal, kilit iadesi, durum). */
    private function finalizeTaker(Order $taker, string $base, string $quote): void
    {
        if ($taker->type === OrderType::Market) {
            // Market emirde artan kısım deftere yazılmaz; kalan kilit iade edilir
            if (BigMath::isPositive($taker->locked_amount)) {
                $asset = $taker->side === OrderSide::Sell ? $base : $quote;
                $this->wallets->unlock($taker->user_id, $asset, $taker->locked_amount, LedgerEntryType::Unlock, $taker);
                $taker->locked_amount = '0';
            }

            $taker->status = BigMath::isPositive($taker->filled_quantity)
                ? OrderStatus::Filled
                : OrderStatus::Cancelled;
            $taker->save();

            return;
        }

        // Limit emir: kalan miktar deftere dinlenmeye kalır
        if (BigMath::isPositive($this->baseRemaining($taker))) {
            $taker->status = BigMath::isPositive($taker->filled_quantity)
                ? OrderStatus::PartiallyFilled
                : OrderStatus::Open;
        } else {
            $taker->status = OrderStatus::Filled;
        }

        $taker->save();
    }

    private function baseRemaining(Order $order): string
    {
        return BigMath::sub($order->quantity, $order->filled_quantity);
    }

    private function quoteBudgetRemaining(Order $order): string
    {
        return BigMath::sub((string) $order->quote_amount, $order->quote_filled);
    }

    private function isMarketBuy(Order $order): bool
    {
        return $order->type === OrderType::Market && $order->side === OrderSide::Buy;
    }
}

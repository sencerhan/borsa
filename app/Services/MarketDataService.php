<?php

namespace App\Services;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Market;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use App\Models\Wallet;
use App\Support\BigMath;
use Illuminate\Support\Collection;

/**
 * Borsa görüntüleme verileri: emir defteri, son işlemler, ticker, kullanıcı emir/bakiyeleri.
 * Sadece okuma; iş kuralı içermez.
 */
class MarketDataService
{
    /**
     * Fiyat seviyelerine göre toplanmış emir defteri.
     *
     * @return array{bids: list<array{price: string, quantity: string, total: string}>, asks: list<array{price: string, quantity: string, total: string}>}
     */
    public function orderBook(Market $market, int $depth = 15): array
    {
        return [
            'bids' => $this->aggregate($market, OrderSide::Buy, $depth),
            'asks' => $this->aggregate($market, OrderSide::Sell, $depth),
        ];
    }

    /**
     * @return list<array{price: string, quantity: string, total: string}>
     */
    private function aggregate(Market $market, OrderSide $side, int $depth): array
    {
        $rows = Order::query()
            ->active()
            ->where('market_id', $market->id)
            ->where('side', $side->value)
            ->get(['price', 'quantity', 'filled_quantity']);

        // Fiyat seviyesine göre kalan miktarı topla
        $levels = [];
        foreach ($rows as $row) {
            $price = (string) $row->price;
            $remaining = BigMath::sub($row->quantity, $row->filled_quantity);
            if (! BigMath::isPositive($remaining)) {
                continue;
            }
            $levels[$price] = isset($levels[$price])
                ? BigMath::add($levels[$price], $remaining)
                : $remaining;
        }

        // Alışta yüksek fiyat önce, satışta düşük fiyat önce
        uksort($levels, fn (string $a, string $b): int => $side === OrderSide::Buy
            ? BigMath::cmp($b, $a)
            : BigMath::cmp($a, $b));

        $result = [];
        $total = '0';
        foreach (array_slice($levels, 0, $depth, true) as $price => $quantity) {
            $total = BigMath::add($total, BigMath::mul($price, $quantity));
            $result[] = [
                'price' => $price,
                'quantity' => $quantity,
                'total' => $total, // kümülatif quote hacmi
            ];
        }

        return $result;
    }

    /**
     * @return Collection<int, Trade>
     */
    public function recentTrades(Market $market, int $limit = 30): Collection
    {
        return $market->trades()
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'price', 'quantity', 'quote_amount', 'taker_side', 'created_at']);
    }

    /**
     * 24 saatlik özet (son fiyat, hacim, yüksek/düşük, değişim).
     *
     * @return array{last_price: string|null, high: string|null, low: string|null, volume: string, change_percent: string|null}
     */
    public function ticker(Market $market): array
    {
        $since = now()->subDay();

        /** @var Collection<int, Trade> $trades */
        $trades = $market->trades()
            ->where('created_at', '>=', $since)
            ->get(['price', 'quantity', 'created_at']);

        $high = null;
        $low = null;
        $volume = '0';
        foreach ($trades as $trade) {
            $price = (string) $trade->price;
            $high = $high === null ? $price : BigMath::max($high, $price);
            $low = $low === null ? $price : BigMath::min($low, $price);
            $volume = BigMath::add($volume, $trade->quantity);
        }

        $first = $trades->first();
        $change = null;
        if ($first !== null && $market->last_price !== null && BigMath::isPositive((string) $first->price)) {
            $diff = BigMath::sub((string) $market->last_price, (string) $first->price);
            $change = BigMath::mul(BigMath::div($diff, (string) $first->price), '100');
        }

        return [
            'last_price' => $market->last_price !== null ? (string) $market->last_price : null,
            'high' => $high,
            'low' => $low,
            'volume' => $volume,
            'change_percent' => $change,
        ];
    }

    /**
     * @return Collection<int, Order>
     */
    public function userOpenOrders(User $user, Market $market): Collection
    {
        return $user->orders()
            ->where('market_id', $market->id)
            ->whereIn('status', [OrderStatus::Open->value, OrderStatus::PartiallyFilled->value])
            ->latest('id')
            ->get();
    }

    /**
     * @return Collection<int, Order>
     */
    public function userOrderHistory(User $user, Market $market, int $limit = 50): Collection
    {
        return $user->orders()
            ->where('market_id', $market->id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Wallet>
     */
    public function userWallets(User $user): Collection
    {
        return $user->wallets()->orderBy('asset')->get();
    }

    /**
     * Kullanıcının toplam portföy değerini quote varlığı (USDT) cinsinden hesaplar.
     * Base varlık son işlem fiyatıyla değerlenir.
     */
    public function portfolioValue(User $user, Market $market): string
    {
        $value = '0';

        foreach ($this->userWallets($user) as $wallet) {
            $total = BigMath::add($wallet->available, $wallet->locked);

            if ($wallet->asset === $market->quote_asset) {
                $value = BigMath::add($value, $total);
            } elseif ($wallet->asset === $market->base_asset && $market->last_price !== null) {
                $value = BigMath::add($value, BigMath::mul($total, (string) $market->last_price));
            }
        }

        return $value;
    }
}

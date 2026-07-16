<?php

namespace App\Services\Admin;

use App\Enums\LedgerEntryType;
use App\Enums\OrderSide;
use App\Enums\OrderType;
use App\Models\Market;
use App\Models\Order;
use App\Models\User;
use App\Services\Data\PlaceOrderData;
use App\Services\OrderService;
use App\Services\WalletService;
use App\Support\BigMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Otonom Piyasa Yapıcı Bot Servisi.
 * Referans fiyatı günceller, eski bot emirlerini siler, yeni limit emirleri ekler,
 * ve hacim oluşturmak için rastgele taker işlemler tetikler.
 */
class MarketBotService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly WalletService $wallets,
    ) {}

    /**
     * Botu belirtilen market için tek bir adım çalıştırır.
     */
    public function tick(Market $market): void
    {
        if (! $market->is_active || ! $market->bot_active) {
            return;
        }

        try {
            // 1. Bot kullanıcılarını bul veya oluştur
            $maker = $this->getOrCreateBotUser('maker@example.com', 'Piyasa Yapıcı');
            $taker = $this->getOrCreateBotUser('bot_taker@example.com', 'Sanal Taker');

            // 2. Bakiyelerini tazele (eğer çok azaldıysa otonom devam etmesi için otomatik fonla)
            $this->ensureBalances($maker);
            $this->ensureBalances($taker);

            // 3. Eski aktif limit emirleri temizle
            $this->cancelOldBotOrders($maker, $market);

            // 4. Referans fiyatı volatiliteye göre güncelle (Random Walk)
            $newRefPrice = $this->calculateNewReferencePrice($market);
            $market->bot_reference_price = $newRefPrice;
            $market->save();

            // 5. Yeni limit alım/satım emirlerini yerleştir (Order Book derinliği)
            $this->placeOrderBookDepth($maker, $market, $newRefPrice);

            // 6. %35 olasılıkla sahte bir trade (taker işlemi) gerçekleştirerek hacim yarat
            if (random_int(1, 100) <= 35) {
                $this->executeRandomTakerTrade($taker, $market, $newRefPrice);
            }

        } catch (\Throwable $e) {
            Log::error("Market bot tick hatası ({$market->symbol}): ".$e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    private function getOrCreateBotUser(string $email, string $name): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => bcrypt('bot-secret-password-12345'),
                'email_verified_at' => now(),
            ]
        );
    }

    private function ensureBalances(User $user): void
    {
        DB::transaction(function () use ($user) {
            $usdtWallet = $this->wallets->getOrCreate($user->id, 'USDT');
            $bkWallet = $this->wallets->getOrCreate($user->id, 'BK$');

            // USDT bakiyesi 10.000'in altına inerse 100.000'e tamamla
            if (BigMath::lt($usdtWallet->available, '10000.000000')) {
                $this->wallets->credit($user->id, 'USDT', '100000.000000', LedgerEntryType::Deposit);
            }

            // BK$ bakiyesi 50.000'in altına inerse 1.000.000'a tamamla
            if (BigMath::lt($bkWallet->available, '50000.000000')) {
                $this->wallets->credit($user->id, 'BK$', '1000000.000000', LedgerEntryType::Deposit);
            }
        });
    }

    private function cancelOldBotOrders(User $maker, Market $market): void
    {
        $activeOrders = Order::query()
            ->active()
            ->where('user_id', $maker->id)
            ->where('market_id', $market->id)
            ->get();

        foreach ($activeOrders as $order) {
            try {
                $this->orders->cancel($maker, $order->id);
            } catch (\Throwable $e) {
                // Hataları logla ama devam et
                Log::warning('Bot emri iptal edilemedi: '.$e->getMessage());
            }
        }
    }

    private function calculateNewReferencePrice(Market $market): string
    {
        $currentPrice = (string) $market->bot_reference_price;
        if (! BigMath::isPositive($currentPrice)) {
            $currentPrice = '0.030000';
        }

        $volatility = (string) $market->bot_volatility; // Örn: 0.005000 (%0.5)

        // Rastgele yön (-1 veya 1) ve oran
        $multiplier = random_int(0, 1) === 1 ? '1' : '-1';
        $randomFactor = (string) (random_int(0, 1000) / 1000); // 0 ile 1 arası

        // Değişim miktarı = currentPrice * volatility * randomFactor * multiplier
        $change = BigMath::mul($currentPrice, $volatility);
        $change = BigMath::mul($change, $randomFactor);
        $change = BigMath::mul($change, $multiplier);

        $newPrice = BigMath::add($currentPrice, $change);

        // Fiyatın sıfırın altına inmesini engelle
        if (BigMath::lte($newPrice, '0.000001')) {
            $newPrice = '0.000001';
        }

        return number_format((float) $newPrice, $market->price_scale, '.', '');
    }

    private function placeOrderBookDepth(User $maker, Market $market, string $refPrice): void
    {
        $baseSpread = (string) $market->bot_spread; // Örn: 0.010000 (%1.0)

        // 3 farklı derinlik seviyesi oluşturalım
        $levels = [
            ['spread_mult' => '1.0', 'qty_mult' => '1.0'],
            ['spread_mult' => '1.5', 'qty_mult' => '1.8'],
            ['spread_mult' => '2.5', 'qty_mult' => '3.0'],
        ];

        foreach ($levels as $level) {
            $levelSpread = BigMath::mul($baseSpread, $level['spread_mult']);

            // Fiyat adımlarını hesapla
            // Buy Price = refPrice * (1 - levelSpread)
            $buyPrice = BigMath::mul($refPrice, BigMath::sub('1', $levelSpread));
            // Sell Price = refPrice * (1 + levelSpread)
            $sellPrice = BigMath::mul($refPrice, BigMath::add('1', $levelSpread));

            // Rastgele miktarlar
            $minQty = (string) $market->bot_min_qty;
            $maxQty = (string) $market->bot_max_qty;

            $diff = BigMath::sub($maxQty, $minQty);
            $randomPercent = (string) (random_int(0, 100) / 100);
            $qty = BigMath::add($minQty, BigMath::mul($diff, $randomPercent));
            $qty = BigMath::mul($qty, $level['qty_mult']);

            // Yuvarlamalar
            $buyPriceStr = number_format((float) $buyPrice, $market->price_scale, '.', '');
            $sellPriceStr = number_format((float) $sellPrice, $market->price_scale, '.', '');
            $qtyStr = number_format((float) $qty, $market->size_scale, '.', '');

            if (BigMath::isPositive($buyPriceStr) && BigMath::isPositive($qtyStr)) {
                try {
                    $this->orders->place($maker, new PlaceOrderData(
                        marketId: $market->id,
                        side: OrderSide::Buy,
                        type: OrderType::Limit,
                        price: $buyPriceStr,
                        quantity: $qtyStr
                    ));
                } catch (\Throwable $e) {
                    Log::warning('Bot limit alım emri veremedi: '.$e->getMessage());
                }
            }

            if (BigMath::isPositive($sellPriceStr) && BigMath::isPositive($qtyStr)) {
                try {
                    $this->orders->place($maker, new PlaceOrderData(
                        marketId: $market->id,
                        side: OrderSide::Sell,
                        type: OrderType::Limit,
                        price: $sellPriceStr,
                        quantity: $qtyStr
                    ));
                } catch (\Throwable $e) {
                    Log::warning('Bot limit satım emri veremedi: '.$e->getMessage());
                }
            }
        }
    }

    private function executeRandomTakerTrade(User $taker, Market $market, string $refPrice): void
    {
        // Alıcı mı satıcı mı olacağını rastgele seç
        $side = random_int(0, 1) === 1 ? OrderSide::Buy : OrderSide::Sell;

        // Küçük bir hacim belirle
        $minQty = (string) $market->bot_min_qty;
        $maxQty = BigMath::mul($minQty, '5'); // Min emrin maks 5 katı taker işlemi olsun

        $diff = BigMath::sub($maxQty, $minQty);
        $randomPercent = (string) (random_int(0, 100) / 100);
        $qty = BigMath::add($minQty, BigMath::mul($diff, $randomPercent));
        $qtyStr = number_format((float) $qty, $market->size_scale, '.', '');

        try {
            if ($side === OrderSide::Buy) {
                // Alıcı ise quoteAmount (USDT tutarı) hesaplanıp girilmeli çünkü market buy
                // QuoteAmount = refPrice * qty
                $quoteAmount = BigMath::mul($refPrice, $qtyStr);
                $quoteAmountStr = number_format((float) $quoteAmount, $market->price_scale, '.', '');

                if (BigMath::isPositive($quoteAmountStr)) {
                    $this->orders->place($taker, new PlaceOrderData(
                        marketId: $market->id,
                        side: OrderSide::Buy,
                        type: OrderType::Market,
                        quoteAmount: $quoteAmountStr
                    ));
                }
            } else {
                // Satıcı ise direkt base miktarı verilir
                if (BigMath::isPositive($qtyStr)) {
                    $this->orders->place($taker, new PlaceOrderData(
                        marketId: $market->id,
                        side: OrderSide::Sell,
                        type: OrderType::Market,
                        quantity: $qtyStr
                    ));
                }
            }
        } catch (\Throwable $e) {
            // Tahta boşsa veya bakiye yetersizse normaldir, sessizce geç
            Log::debug('Bot taker işlemi başarısız: '.$e->getMessage());
        }
    }
}

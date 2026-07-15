<?php

namespace Tests\Concerns;

use App\Enums\LedgerEntryType;
use App\Models\Asset;
use App\Models\Market;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;

trait ExchangeTestHelpers
{
    /** BK$/USDT piyasasını ve varlıkları oluşturur. */
    protected function makeMarket(array $overrides = []): Market
    {
        Asset::firstOrCreate(['symbol' => 'BK$'], ['name' => 'BitKuruş', 'scale' => 8]);
        Asset::firstOrCreate(['symbol' => 'USDT'], ['name' => 'Tether', 'scale' => 6]);

        return Market::create(array_merge([
            'symbol' => 'BK$/USDT',
            'base_asset' => 'BK$',
            'quote_asset' => 'USDT',
            'maker_fee' => '0.001000',
            'taker_fee' => '0.002000',
            'min_order_size' => '0',
            'price_scale' => 6,
            'size_scale' => 2,
            'is_active' => true,
        ], $overrides));
    }

    /** Kullanıcının bir varlıkta serbest bakiyesini artırır. */
    protected function fund(User $user, string $asset, string $amount): void
    {
        app(WalletService::class)->credit($user->id, $asset, $amount, LedgerEntryType::Deposit);
    }

    /** Kullanıcının bir varlıktaki cüzdanını döndürür. */
    protected function wallet(User $user, string $asset): Wallet
    {
        return Wallet::where('user_id', $user->id)->where('asset', $asset)->firstOrFail();
    }

    /** İki yüksek hassasiyetli sayının eşitliğini doğrular. */
    protected function assertBalance(string $expected, string $actual, string $message = ''): void
    {
        $this->assertSame(0, bccomp($expected, $actual, 18), $message !== '' ? $message : "Beklenen {$expected}, gelen {$actual}");
    }
}

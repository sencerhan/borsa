<?php

namespace Database\Seeders;

use App\Enums\LedgerEntryType;
use App\Enums\OrderSide;
use App\Enums\OrderType;
use App\Models\Asset;
use App\Models\Market;
use App\Models\User;
use App\Services\Data\PlaceOrderData;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $wallets = app(WalletService::class);
        $orders = app(OrderService::class);

        // Varlıklar
        Asset::updateOrCreate(['symbol' => 'BK$'], ['name' => 'BitKuruş', 'network' => 'BK Chain', 'scale' => 8]);
        Asset::updateOrCreate(['symbol' => 'USDT'], ['name' => 'Tether', 'network' => 'TRC20', 'scale' => 6]);

        // Tek market: BK$/USDT
        $market = Market::updateOrCreate(
            ['symbol' => 'BK$/USDT'],
            [
                'base_asset' => 'BK$',
                'quote_asset' => 'USDT',
                'maker_fee' => '0.001000', // %0.1
                'taker_fee' => '0.002000', // %0.2
                'min_order_size' => '1',
                'price_scale' => 6,
                'size_scale' => 2,
                'is_active' => true,
            ],
        );

        // Admin kullanıcı (is_admin mass-assignable değil → forceFill)
        $admin = User::factory()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
        $admin->forceFill(['is_admin' => true])->save();

        // Test kullanıcıları
        $test = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);
        $maker = User::factory()->create(['name' => 'Piyasa Yapıcı', 'email' => 'maker@example.com']);

        // Başlangıç bakiyeleri
        foreach ([$admin, $test, $maker] as $user) {
            $wallets->credit($user->id, 'USDT', '100000', LedgerEntryType::Deposit);
            $wallets->credit($user->id, 'BK$', '1000000', LedgerEntryType::Deposit);
        }

        // Örnek emir defteri (maker kullanıcısından limit emirler)
        $orders->place($maker, new PlaceOrderData($market->id, OrderSide::Sell, OrderType::Limit, price: '0.032000', quantity: '5000'));
        $orders->place($maker, new PlaceOrderData($market->id, OrderSide::Sell, OrderType::Limit, price: '0.031000', quantity: '3000'));
        $orders->place($maker, new PlaceOrderData($market->id, OrderSide::Buy, OrderType::Limit, price: '0.029000', quantity: '4000'));
        $orders->place($maker, new PlaceOrderData($market->id, OrderSide::Buy, OrderType::Limit, price: '0.028000', quantity: '6000'));
    }
}

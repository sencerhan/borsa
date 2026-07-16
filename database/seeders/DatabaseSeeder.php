<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Market;
use App\Models\User;
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
        $admin = User::factory()->create(['name' => 'Admin', 'email' => 'admin@sencerhan.com']);
        $admin->forceFill(['is_admin' => true])->save();
    }
}

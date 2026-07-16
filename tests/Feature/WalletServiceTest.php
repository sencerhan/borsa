<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): WalletService
    {
        return app(WalletService::class);
    }

    public function test_bk_chain_deposit_address_is_64_char_lowercase_hex(): void
    {
        // BK$ ağı: Ed25519 public key -> 64 karakter küçük harf hex, prefix yok
        // Kaynak: https://bitkurush.org/docs
        Asset::create(['symbol' => 'BK$', 'name' => 'BitKuruş', 'network' => 'BK Chain', 'scale' => 8]);
        $user = User::factory()->create();

        $wallet = $this->service()->getOrCreate($user->id, 'BK$');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $wallet->deposit_address);
    }

    public function test_trc20_deposit_address_uses_t_prefix(): void
    {
        Asset::create(['symbol' => 'USDT', 'name' => 'Tether', 'network' => 'TRC20', 'scale' => 6]);
        $user = User::factory()->create();

        $wallet = $this->service()->getOrCreate($user->id, 'USDT');

        $this->assertMatchesRegularExpression('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $wallet->deposit_address);
    }

    public function test_evm_deposit_address_uses_0x_prefix(): void
    {
        Asset::create(['symbol' => 'ETH', 'name' => 'Ethereum', 'network' => 'ERC20', 'scale' => 18]);
        $user = User::factory()->create();

        $wallet = $this->service()->getOrCreate($user->id, 'ETH');

        $this->assertMatchesRegularExpression('/^0x[0-9a-f]{40}$/', $wallet->deposit_address);
    }
}

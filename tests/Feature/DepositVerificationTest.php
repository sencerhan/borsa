<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Deposit;
use App\Models\User;
use App\Models\Wallet;
use App\Services\DepositVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DepositVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function service(): DepositVerificationService
    {
        return app(DepositVerificationService::class);
    }

    private function bkWallet(string $address): Wallet
    {
        Asset::create(['symbol' => 'BK$', 'name' => 'BitKuruş', 'network' => 'BK Chain', 'scale' => 8]);
        $user = User::factory()->create();

        return Wallet::create([
            'user_id' => $user->id,
            'asset' => 'BK$',
            'deposit_address' => $address,
            'available' => '0',
            'locked' => '0',
        ]);
    }

    public function test_bk_active_token_is_credited_with_real_amount(): void
    {
        $address = str_repeat('a', 64);
        $wallet = $this->bkWallet($address);

        // Gelen para 'split' origin'li aktif bir token (UTXO) olarak durur.
        Http::fake([
            'bitkurush.org/*' => Http::response([
                'result' => 'ok',
                'data' => [
                    'balance' => '125.500000',
                    'tokens' => [
                        ['token_id' => 'utxo-001', 'value' => '125.500000', 'status' => 'active', 'origin' => 'split', 'created_at' => '2026-07-16T00:00:00.000000Z'],
                        // Harcanmış (spent) token kredilenmez
                        ['token_id' => 'utxo-002', 'value' => '999.000000', 'status' => 'spent', 'created_at' => '2026-07-16T00:00:00.000000Z'],
                    ],
                ],
            ]),
        ]);

        $credited = $this->service()->scanWallet($wallet);

        $this->assertSame(1, $credited);
        // Sadece 'active' token kredilenir, 'spent' yok sayılır
        $this->assertSame('125.500000000000000000', $wallet->fresh()->available);
        $this->assertDatabaseHas('deposits', ['txid' => 'utxo-001', 'asset' => 'BK$', 'status' => 'completed']);
        $this->assertDatabaseMissing('deposits', ['txid' => 'utxo-002']);
    }

    public function test_bk_deposit_is_idempotent_across_scans(): void
    {
        $address = str_repeat('b', 64);
        $wallet = $this->bkWallet($address);

        Http::fake([
            'bitkurush.org/*' => Http::response([
                'result' => 'ok',
                'data' => ['tokens' => [
                    ['token_id' => 'utxo-dup', 'value' => '10.000000', 'status' => 'active', 'created_at' => '2026-07-16T00:00:00.000000Z'],
                ]],
            ]),
        ]);

        $this->service()->scanWallet($wallet);
        $secondRun = $this->service()->scanWallet($wallet);

        // İkinci taramada aynı token_id tekrar kredilenmez
        $this->assertSame(0, $secondRun);
        $this->assertSame(1, Deposit::where('txid', 'utxo-dup')->count());
        $this->assertSame('10.000000000000000000', $wallet->fresh()->available);
    }

    public function test_usdt_trc20_incoming_transfer_is_credited(): void
    {
        $contract = (string) config('services.trongrid.usdt_contract');
        $address = 'TMKRFcuuzxfuuGzmgnpT5AizXpzaK3L5xt';

        Asset::create(['symbol' => 'USDT', 'name' => 'Tether', 'network' => 'TRC20', 'scale' => 6]);
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'asset' => 'USDT',
            'deposit_address' => $address,
            'available' => '0',
            'locked' => '0',
        ]);

        Http::fake([
            'api.trongrid.io/*' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'transaction_id' => 'tron-tx-1',
                        'to' => $address,
                        'value' => '1500000', // 1.5 USDT (6 hane)
                        'token_info' => ['address' => $contract, 'decimals' => 6, 'symbol' => 'USDT'],
                        'block_timestamp' => 1700000000000,
                    ],
                ],
            ]),
        ]);

        $credited = $this->service()->scanWallet($wallet);

        $this->assertSame(1, $credited);
        $this->assertSame('1.500000000000000000', $wallet->fresh()->available);
        $this->assertDatabaseHas('deposits', ['txid' => 'tron-tx-1', 'asset' => 'USDT']);
    }

    public function test_internal_change_token_is_not_credited_as_deposit(): void
    {
        $address = str_repeat('d', 64);
        $wallet = $this->bkWallet($address);

        // Çekim para üstü olarak borsanın kendi ürettiği token.
        \App\Models\ChainInternalToken::mark('BK$', 'utxo-change');

        Http::fake([
            'bitkurush.org/*' => Http::response([
                'result' => 'ok',
                'data' => ['tokens' => [
                    ['token_id' => 'utxo-change', 'value' => '0.500000', 'status' => 'active', 'origin' => 'split', 'created_at' => '2026-07-16T00:00:00.000000Z'],
                ]],
            ]),
        ]);

        $credited = $this->service()->scanWallet($wallet);

        // Change token yatırma sayılmaz
        $this->assertSame(0, $credited);
        $this->assertSame(0, bccomp($wallet->fresh()->available, '0', 18));
        $this->assertDatabaseMissing('deposits', ['txid' => 'utxo-change']);
    }

    public function test_zero_amount_is_ignored(): void
    {
        $address = str_repeat('c', 64);
        $wallet = $this->bkWallet($address);

        Http::fake([
            'bitkurush.org/*' => Http::response([
                'result' => 'ok',
                'data' => ['tokens' => [
                    ['token_id' => 'utxo-zero', 'value' => '0', 'status' => 'active', 'created_at' => '2026-07-16T00:00:00.000000Z'],
                ]],
            ]),
        ]);

        $credited = $this->service()->scanWallet($wallet);

        $this->assertSame(0, $credited);
        $this->assertSame(0, bccomp($wallet->fresh()->available, '0', 18));
        $this->assertDatabaseMissing('deposits', ['txid' => 'utxo-zero']);
    }
}

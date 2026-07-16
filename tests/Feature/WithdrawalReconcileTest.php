<?php

namespace Tests\Feature;

use App\Enums\WithdrawalStatus;
use App\Models\Asset;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use App\Support\BkKeypair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WithdrawalReconcileTest extends TestCase
{
    use RefreshDatabase;

    private function bkUserWithBalance(string $balance): User
    {
        Asset::firstOrCreate(['symbol' => 'BK$'], ['name' => 'BitKuruş', 'network' => 'BK Chain', 'scale' => 8]);
        $user = User::factory()->create();

        $kp = BkKeypair::generate();
        Wallet::create([
            'user_id' => $user->id,
            'asset' => 'BK$',
            'deposit_address' => $kp['address'],
            'deposit_secret' => Crypt::encryptString($kp['secret']),
            'available' => $balance,
            'locked' => '0',
        ]);

        return $user;
    }

    public function test_bk_withdrawal_completes_through_process(): void
    {
        Queue::fake();
        $user = $this->bkUserWithBalance('100');

        // Zincir: adreste yeterli aktif token; submit kabul; işlem committed.
        Http::fake([
            'bitkurush.org/api/wallet/*' => Http::response(['result' => 'ok', 'data' => ['tokens' => [
                ['token_id' => 'utxo-1', 'value' => '100.000000000000000000', 'status' => 'active', 'version' => 1],
            ]]]),
            'bitkurush.org/api/tx/submit' => Http::response(['result' => 'ok'], 202),
            'bitkurush.org/api/tx/*' => Http::response(['data' => ['status' => 'committed']]),
        ]);

        $withdrawals = app(WithdrawalService::class);
        $w = $withdrawals->request($user, 'BK$', '10', str_repeat('b', 64));

        // Rezerve
        $this->assertSame(0, bccomp('90', Wallet::where('user_id', $user->id)->where('asset', 'BK$')->value('available'), 18));

        $withdrawals->process($w->id);

        $fresh = $w->fresh();
        $this->assertSame(WithdrawalStatus::Completed, $fresh->status);
        $this->assertNotNull($fresh->txid);
        // Bakiye rezerve edilmiş halde kalır (iade yok)
        $this->assertSame(0, bccomp('90', Wallet::where('user_id', $user->id)->where('asset', 'BK$')->value('available'), 18));
    }

    public function test_reconcile_completes_processing_with_committed_txid(): void
    {
        $user = $this->bkUserWithBalance('90');
        $w = Withdrawal::create([
            'user_id' => $user->id, 'asset' => 'BK$', 'amount' => '10',
            'address' => str_repeat('b', 64), 'status' => WithdrawalStatus::Processing,
            'reference' => 'WD-X', 'txid' => 'wallet-tx-inflight',
        ]);
        Withdrawal::whereKey($w->id)->update(['updated_at' => now()->subMinutes(10)]);

        Http::fake(['bitkurush.org/api/tx/*' => Http::response(['data' => ['status' => 'committed']])]);

        $resolved = app(WithdrawalService::class)->reconcile();

        $this->assertSame(1, $resolved);
        $this->assertSame(WithdrawalStatus::Completed, $w->fresh()->status);
    }

    public function test_reconcile_refunds_processing_with_rejected_txid(): void
    {
        $user = $this->bkUserWithBalance('90');
        $w = Withdrawal::create([
            'user_id' => $user->id, 'asset' => 'BK$', 'amount' => '10',
            'address' => str_repeat('b', 64), 'status' => WithdrawalStatus::Processing,
            'reference' => 'WD-Y', 'txid' => 'wallet-tx-bad',
        ]);
        Withdrawal::whereKey($w->id)->update(['updated_at' => now()->subMinutes(10)]);

        Http::fake(['bitkurush.org/api/tx/*' => Http::response(['data' => ['status' => 'rejected']])]);

        app(WithdrawalService::class)->reconcile();

        $this->assertSame(WithdrawalStatus::Failed, $w->fresh()->status);
        // İade: 90 + 10 = 100
        $this->assertSame(0, bccomp('100', Wallet::where('user_id', $user->id)->where('asset', 'BK$')->value('available'), 18));
    }

    public function test_reconcile_refunds_processing_without_txid_when_stale(): void
    {
        $user = $this->bkUserWithBalance('90');
        $w = Withdrawal::create([
            'user_id' => $user->id, 'asset' => 'BK$', 'amount' => '10',
            'address' => str_repeat('b', 64), 'status' => WithdrawalStatus::Processing,
            'reference' => 'WD-Z', 'txid' => null,
        ]);
        Withdrawal::whereKey($w->id)->update(['updated_at' => now()->subMinutes(10)]);

        // txid yok → ödeme hiç yayınlanmadı → güvenli iade
        app(WithdrawalService::class)->reconcile();

        $this->assertSame(WithdrawalStatus::Failed, $w->fresh()->status);
        $this->assertSame(0, bccomp('100', Wallet::where('user_id', $user->id)->where('asset', 'BK$')->value('available'), 18));
    }

    public function test_reconcile_leaves_recent_processing_untouched(): void
    {
        $user = $this->bkUserWithBalance('90');
        $w = Withdrawal::create([
            'user_id' => $user->id, 'asset' => 'BK$', 'amount' => '10',
            'address' => str_repeat('b', 64), 'status' => WithdrawalStatus::Processing,
            'reference' => 'WD-R', 'txid' => null,
        ]);
        // updated_at şimdi → eşik altındaki taze kayıt dokunulmamalı

        $resolved = app(WithdrawalService::class)->reconcile();

        $this->assertSame(0, $resolved);
        $this->assertSame(WithdrawalStatus::Processing, $w->fresh()->status);
    }
}
